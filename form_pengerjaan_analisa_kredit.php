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
        $required = ['tanggal','nama_debitur','plafond','jangka_waktu','usaha','jenis_permohonan','bunga'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) throw new Exception("Field '{$f}' wajib diisi.");
        }

        $sql = "INSERT INTO trx_analisa_kredit 
                (pegawai_id, nama_calon_debitur, plafond_dimohon, jangka_waktu, usaha, jenis_permohonan,
                 suku_bunga, tanggal_pengerjaan, keperluan_kunjungan, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $id_pegawai,
            $_POST['nama_debitur'], $_POST['plafond'], $_POST['jangka_waktu'],
            $_POST['usaha'], $_POST['jenis_permohonan'], $_POST['bunga'],
            $_POST['tanggal'], $_POST['keperluan'] ?? '', $_POST['keterangan'] ?? ''
        ]);

        $_SESSION['status_simpan'] = "sukses";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        $status_simpan = "error";
        $pesan_error   = $e->getMessage();
        error_log("[ANALISA KREDIT ERROR] " . $e->getMessage());
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
    <title>Form Analisa Kredit - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card-form { border: none; border-radius: 15px; box-shadow: 0 4px 25px rgba(25,135,84,0.08); }
        .card-header-green { background-color: #fff; border-bottom: 2px solid #198754; color: #198754; border-radius: 15px 15px 0 0 !important; }
        .form-label { font-weight: 700; color: #146c43; font-size: 0.85rem; text-transform: uppercase; }
        .form-control:focus, .form-select:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25,135,84,0.15); }
        .btn-save { background-color: #198754; border: none; color: white; font-weight: bold; padding: 12px 30px; border-radius: 10px; transition: 0.3s; }
        .btn-save:hover { background-color: #0d5a36; box-shadow: 0 4px 12px rgba(25,135,84,0.3); color: white; }
        .section-title { color: #198754; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-bottom: 15px; font-weight: 600; }
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
            <h5 class="mb-0 fw-bold"><i class="bi bi-calculator me-2"></i> FORM PENGERJAAN ANALISA KREDIT</h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST" id="formAnalisa">
                <div class="row">
                    <div class="col-12"><h6 class="section-title">Informasi Debitur & Waktu</h6></div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nama Calon Debitur</label>
                        <input type="text" name="nama_debitur" class="form-control" placeholder="Masukkan nama lengkap calon debitur..." value="<?= htmlspecialchars($_POST['nama_debitur'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Pengerjaan</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                    </div>

                    <div class="col-12 mt-3"><h6 class="section-title">Fasilitas Kredit yang Dimohon</h6></div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Plafond Dimohon (Rp)</label>
                        <input type="number" name="plafond" class="form-control border-success" placeholder="Contoh: 50000000" value="<?= htmlspecialchars($_POST['plafond'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jangka Waktu</label>
                        <input type="text" name="jangka_waktu" class="form-control" placeholder="Contoh: 36 Bulan" value="<?= htmlspecialchars($_POST['jangka_waktu'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Suku Bunga</label>
                        <input type="text" name="bunga" class="form-control" placeholder="Contoh: 1.25% Flat" value="<?= htmlspecialchars($_POST['bunga'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Usaha / Pekerjaan</label>
                        <input type="text" name="usaha" class="form-control" placeholder="Bidang usaha debitur..." value="<?= htmlspecialchars($_POST['usaha'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jenis Permohonan</label>
                        <select name="jenis_permohonan" class="form-select" required>
                            <option value="">-- Pilih Jenis --</option>
                            <?php
                            try {
                                $q = $conn->query("SELECT * FROM reff_jenis_permohonan ORDER BY nama_permohonan ASC");
                                while ($r = $q->fetch()) {
                                    $sel = (isset($_POST['jenis_permohonan']) && $_POST['jenis_permohonan'] == $r['nama_permohonan']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($r['nama_permohonan'])."' $sel>".htmlspecialchars($r['nama_permohonan'])."</option>";
                                }
                            } catch (PDOException $e) { echo "<option value=''>Gagal memuat</option>"; }
                            ?>
                        </select>
                    </div>

                    <div class="col-12 mt-3"><h6 class="section-title">Hasil Analisa Lapangan (OTS)</h6></div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Keperluan Kunjungan / Lokasi Analisa</label>
                        <textarea name="keperluan" class="form-control" rows="2" placeholder="Sebutkan lokasi OTS dan apa saja yang diverifikasi..."><?= htmlspecialchars($_POST['keperluan'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-4">
                        <label class="form-label">Keterangan / Kesimpulan Analisa</label>
                        <textarea name="keterangan" class="form-control" rows="4" placeholder="Tuliskan poin penting hasil analisa kelayakan debitur..."><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 border-top pt-4 text-center">
                        <button type="reset" class="btn btn-outline-secondary px-4 me-2 rounded-pill">RESET</button>
                        <button type="submit" class="btn btn-save px-5 shadow-sm rounded-pill" id="btnSimpan">
                            <i class="bi bi-check-circle-fill me-2"></i> SIMPAN ANALISA
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
    Swal.fire({ icon:'success', title:'Berhasil!', text:'Data Analisa Kredit Berhasil Disimpan!', confirmButtonColor:'#198754' });
    <?php endif; ?>
    <?php if ($status_simpan === "error"): ?>
    Swal.fire({ icon:'error', title:'Gagal Menyimpan!', text:'<?= addslashes($pesan_error) ?>', footer:'<span class="text-danger">Data inputan Anda tetap aman di form.</span>', confirmButtonColor:'#d33' });
    <?php endif; ?>

    document.getElementById('formAnalisa').addEventListener('submit', function () {
        const btn = document.getElementById('btnSimpan');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
    });
});
</script>
</body>
</html>