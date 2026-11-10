<?php
session_start();

// 1. Proteksi Session
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// === PATCH: Paksa PDO error mode ke EXCEPTION ===
try { $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Exception $e) {}

$id_pegawai   = $_SESSION['pegawai_id'];
$nama_display = $_SESSION['nama_user'] ?? "Guest";

// Tentukan link "kembali" sesuai role (atasan kembali ke dashboard atasan)
$link_kembali = ($_SESSION['role'] ?? '') === 'atasan' ? 'dashboard_atasan.php' : 'dashboard_form_kegiatan.php';

$status_simpan = "";
$pesan_error   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validasi field wajib
        $required = ['tanggal','rencana_kegiatan','nama_nasabah','alamat_desa','kecamatan','kabupaten_kota','no_hp','hasil_yang_diharapkan'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) throw new Exception("Field '{$f}' wajib diisi.");
        }

        $sql = "INSERT INTO trx_rencana_kegiatan 
                (pegawai_id, tanggal, rencana_kegiatan, nama_nasabah, alamat_desa, kecamatan, kabupaten_kota, no_hp, hasil_yang_diharapkan, catatan) 
                VALUES (:id_pegawai, :tanggal, :kegiatan, :nasabah, :alamat, :kec, :kab, :hp, :hasil, :catatan)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_pegawai' => $id_pegawai,
            ':tanggal'    => $_POST['tanggal'],
            ':kegiatan'   => $_POST['rencana_kegiatan'],
            ':nasabah'    => $_POST['nama_nasabah'],
            ':alamat'     => $_POST['alamat_desa'],
            ':kec'        => $_POST['kecamatan'],
            ':kab'        => $_POST['kabupaten_kota'],
            ':hp'         => $_POST['no_hp'],
            ':hasil'      => $_POST['hasil_yang_diharapkan'],
            ':catatan'    => $_POST['catatan'] ?? '',
        ]);

        $_SESSION['status_simpan'] = "sukses";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        $status_simpan = "error";
        $pesan_error   = $e->getMessage();
        error_log("[RENCANA KEGIATAN ERROR] " . $e->getMessage());
    }
}

$notif_sukses = false;
if (isset($_SESSION['status_simpan'])) {
    $notif_sukses = true;
    unset($_SESSION['status_simpan']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Rencana Kegiatan - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card-form { border: none; border-radius: 15px; box-shadow: 0 4px 25px rgba(25,135,84,0.08); }
        .card-header-green { background-color: #fff; border-bottom: 2px solid #198754; color: #198754; border-radius: 15px 15px 0 0 !important; }
        .form-label { font-weight: 700; color: #146c43; font-size: 0.85rem; text-transform: uppercase; }
        .form-control:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25,135,84,0.15); }
        .btn-save { background-color: #198754; border: none; color: white; font-weight: bold; padding: 12px 30px; border-radius: 10px; transition: 0.3s; }
        .btn-save:hover { background-color: #0d5a36; box-shadow: 0 4px 12px rgba(25,135,84,0.3); color: white; }
        .section-title { color: #198754; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-bottom: 15px; font-weight: 600; font-size: 0.95rem; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand small fw-bold" href="<?= $link_kembali ?>"><i class="bi bi-arrow-left-circle me-1"></i> KEMBALI</a>
        <span class="navbar-text text-white"><i class="bi bi-person-fill me-1"></i> PETUGAS: <strong><?= htmlspecialchars($nama_display) ?></strong></span>
    </div>
</nav>

<div class="container mb-5">
    <div class="card card-form shadow-sm border-0">
        <div class="card-header card-header-green py-3 text-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i> FORM RENCANA KEGIATAN AO/MARKETING</h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST" id="formRencana">
                <div class="row">
                    <div class="col-12"><h6 class="section-title">Waktu & Identitas Nasabah</h6></div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tanggal Rencana</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Nasabah / Prospek</label>
                        <input type="text" name="nama_nasabah" class="form-control" placeholder="Nama lengkap nasabah" value="<?= htmlspecialchars($_POST['nama_nasabah'] ?? '') ?>" required>
                    </div>

                    <div class="col-12 mt-2"><h6 class="section-title">Lokasi Kunjungan</h6></div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alamat / Desa</label>
                        <input type="text" name="alamat_desa" class="form-control" placeholder="Isi alamat atau desa" value="<?= htmlspecialchars($_POST['alamat_desa'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No HP / WhatsApp</label>
                        <input type="tel" name="no_hp" class="form-control" placeholder="Contoh: 08123456789" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kecamatan</label>
                        <input type="text" name="kecamatan" class="form-control" placeholder="Contoh: Kawedanan" value="<?= htmlspecialchars($_POST['kecamatan'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kabupaten / Kota</label>
                        <input type="text" name="kabupaten_kota" class="form-control" placeholder="Contoh: Magetan" value="<?= htmlspecialchars($_POST['kabupaten_kota'] ?? '') ?>" required>
                    </div>

                    <div class="col-12 mt-2"><h6 class="section-title">Detail Rencana & Target</h6></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Rencana Kegiatan</label>
                        <textarea name="rencana_kegiatan" class="form-control" rows="3" placeholder="Apa yang akan dilakukan di lokasi?" required><?= htmlspecialchars($_POST['rencana_kegiatan'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Hasil yang Diharapkan</label>
                        <textarea name="hasil_yang_diharapkan" class="form-control" rows="3" placeholder="Target yang ingin dicapai dari kunjungan ini?" required><?= htmlspecialchars($_POST['hasil_yang_diharapkan'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-4">
                        <label class="form-label">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan internal jika ada..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 border-top pt-4 text-center">
                        <button type="reset" class="btn btn-outline-secondary px-4 me-2 rounded-pill">RESET</button>
                        <button type="submit" class="btn btn-save px-5 shadow-sm rounded-pill" id="btnSimpan">
                            <i class="bi bi-cloud-arrow-up-fill me-2"></i> SIMPAN RENCANA KEGIATAN
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($notif_sukses): ?>
    Swal.fire({ icon:'success', title:'Berhasil!', text:'Data Rencana Kegiatan Berhasil Disimpan!', confirmButtonColor:'#198754' });
    <?php endif; ?>
    <?php if ($status_simpan === "error"): ?>
    Swal.fire({ icon:'error', title:'Gagal Menyimpan!', text:'<?= addslashes($pesan_error) ?>', confirmButtonColor:'#d33' });
    <?php endif; ?>

    document.getElementById('formRencana').addEventListener('submit', function () {
        const btn = document.getElementById('btnSimpan');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
    });
});
</script>
</body>
</html>