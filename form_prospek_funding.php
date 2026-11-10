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
        // --- Mapping Kepala Kas ---
        $kantor_kas  = $_POST['kantor_kas'] ?? '';
        $kas         = strtolower($kantor_kas);
        $nama_kepala = "";

        if      (strpos($kas, "sumoroto")  !== false) $nama_kepala = "WANDA MIFTAKHUL JANNAH";
        elseif  (strpos($kas, "ngariboyo") !== false || strpos($kas, "parang")     !== false) $nama_kepala = "VITRI DWI CAHYANI";
        elseif  (strpos($kas, "dolopo")    !== false || strpos($kas, "jiwan")      !== false) $nama_kepala = "Asrul Anggriawan Azis";
        elseif  (strpos($kas, "rejoso")    !== false) $nama_kepala = "agus sunaryo";
        elseif  (strpos($kas, "takeran")   !== false || strpos($kas, "lembeyan")   !== false) $nama_kepala = "PIGGY MEI AYYUDHI";
        elseif  (strpos($kas, "bendo")     !== false || strpos($kas, "maospati")   !== false) $nama_kepala = "SUPRAPTO";
        elseif  (strpos($kas, "karas")     !== false || strpos($kas, "karangrejo") !== false || strpos($kas, "barat") !== false) $nama_kepala = "ANDIKA DEVI RISMIANTO";
        elseif  (strpos($kas, "panekan")   !== false || strpos($kas, "magetan")    !== false) $nama_kepala = "ZEQI IRAWAN";

        $id_kepala_kas = null;
        if ($nama_kepala !== "") {
            $stmt_id = $conn->prepare("SELECT id FROM pegawai WHERE nama LIKE ? LIMIT 1");
            $stmt_id->execute(["%$nama_kepala%"]);
            $kepala = $stmt_id->fetch();
            if ($kepala) $id_kepala_kas = $kepala['id'];
        }

        // --- Upload Foto --- support dua jalur:
        // 1. foto_b64: sudah dikompres JS → simpan langsung dari base64
        // 2. bukti_kegiatan: upload file biasa (fallback)
        $nama_file = "";
        if (!empty($_POST['foto_b64'])) {
            $b64data = $_POST['foto_b64'];
            if (strlen($b64data) > 700000) {
                throw new Exception("Foto masih terlalu besar setelah kompresi. Coba foto lain.");
            }
            $decoded = base64_decode($b64data);
            if ($decoded === false) throw new Exception("Data foto tidak valid (base64 rusak).");
            $ext = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['foto_ext'] ?? 'jpg'));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) $ext = 'jpg';
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $nama_file = "FND_" . time() . "_" . mt_rand(1000,9999) . ".jpg";
            if (file_put_contents($target_dir . $nama_file, $decoded) === false) {
                throw new Exception("Gagal menyimpan foto ke server.");
            }
        } elseif (!empty($_FILES['bukti_kegiatan']['name'])) {
            $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
            $ftype = mime_content_type($_FILES['bukti_kegiatan']['tmp_name']);
            if (!in_array($ftype, $allowed_types)) throw new Exception("Tipe file foto tidak didukung.");
            if ($_FILES['bukti_kegiatan']['size'] > 5242880) throw new Exception("Ukuran foto maks 5MB.");
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $ext       = pathinfo($_FILES['bukti_kegiatan']['name'], PATHINFO_EXTENSION);
            $nama_file = "FND_" . time() . "_" . mt_rand(1000,9999) . "." . $ext;
            if (!move_uploaded_file($_FILES['bukti_kegiatan']['tmp_name'], $target_dir . $nama_file)) {
                throw new Exception("Gagal mengunggah foto ke server.");
            }
        }

        // --- Validasi field wajib ---
        $required = ['tanggal','kantor_kas','nama_prospek','jenis_prospek','no_hp','usaha','alamat','kec','kab','estimasi','produk','ketertarikan','hasil','status'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) throw new Exception("Field '{$f}' wajib diisi.");
        }

        $sql = "INSERT INTO trx_prospek_funding 
                (pegawai_id, id_kepala_kas_lokasi, tanggal_kunjungan, nama_prospek, jenis_prospek, no_hp,
                 alamat_desa, kecamatan, kabupaten_kota, pekerjaan_usaha, estimasi_dana, produk_ditawarkan,
                 ketertarikan_nasabah, hasil_kunjungan, status_prospek, catatan_marketing, bukti_kegiatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $id_pegawai, $id_kepala_kas,
            $_POST['tanggal'], $_POST['nama_prospek'], $_POST['jenis_prospek'], $_POST['no_hp'],
            $_POST['alamat'], $_POST['kec'], $_POST['kab'], $_POST['usaha'],
            $_POST['estimasi'], $_POST['produk'], $_POST['ketertarikan'],
            $_POST['hasil'], $_POST['status'], $_POST['catatan'] ?? '', $nama_file
        ]);

        // Jika request dari fetch (AJAX), kirim JSON — bukan redirect
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'sukses']);
            exit;
        }

        $_SESSION['status_simpan'] = "sukses";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        $status_simpan = "error";
        $pesan_error   = $e->getMessage();
        error_log("[PROSPEK FUNDING ERROR] " . $e->getMessage());

        // Jika request dari fetch, kirim JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
            exit;
        }
    }
}

$notif_sukses = false;
if (isset($_SESSION['status_simpan'])) {
    $notif_sukses = true;
    unset($_SESSION['status_simpan']);
}

$kas_list = [
    "KCU"=>"Kantor Cabang Utama","Madiun"=>"Cabang Madiun","Ngawi"=>"Cabang Ngawi",
    "Ponorogo"=>"Cabang Ponorogo","Trenggalek"=>"Cabang Trenggalek","Nganjuk"=>"Cabang Nganjuk",
    "Magetan"=>"Kantor Kas Magetan","Sumoroto"=>"Kantor Kas Sumoroto","Ngariboyo"=>"Kantor Kas Ngariboyo",
    "Parang"=>"Kantor Kas Parang","Dolopo"=>"Kantor Kas Dolopo","Jiwan"=>"Kantor Kas Jiwan",
    "Rejoso"=>"Kantor Kas Rejoso","Takeran"=>"Kantor Kas Takeran","Lembeyan"=>"Kantor Kas Lembeyan",
    "Bendo"=>"Kantor Kas Bendo","Maospati"=>"Kantor Kas Maospati","Karas"=>"Kantor Kas Karas",
    "Karangrejo"=>"Kantor Kas Karangrejo","Barat"=>"Kantor Kas Barat","Panekan"=>"Kantor Kas Panekan",
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Prospek Funding - BPR Hijau</title>
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
        .input-highlight { background-color: #fff3cd; border: 2px solid #ffc107; }
        .btn-save { background-color: #198754; border: none; color: white; font-weight: bold; padding: 12px 30px; border-radius: 10px; transition: 0.3s; }
        .btn-save:hover { background-color: #0d5a36; box-shadow: 0 4px 12px rgba(25,135,84,0.3); color: white; }
        .section-title { color: #198754; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-bottom: 15px; font-weight: 600; font-size: 0.95rem; }
        #fotoPreview { max-height: 120px; border-radius: 8px; margin-top: 8px; display: none; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand small fw-bold" href="<?= $link_kembali ?>"><i class="bi bi-arrow-left-circle me-1"></i> KEMBALI</a>
        <span class="navbar-text text-white"><i class="bi bi-person-fill me-1"></i> Petugas: <strong><?= htmlspecialchars($nama_display) ?></strong></span>
    </div>
</nav>

<div class="container mb-5">
    <div class="card card-form shadow-sm border-0">
        <div class="card-header card-header-green py-3 text-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-person-add me-2"></i> FORM PROSPEK FUNDING</h5>
        </div>
        <div class="card-body p-4">
            <form action="" method="POST" enctype="multipart/form-data" id="formFunding">
                <div class="row">
                    <div class="col-12"><h6 class="section-title">Informasi Dasar & Penugasan Kas</h6></div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal Kunjungan</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label text-danger">Kantor Kas Transaksi / Domisili</label>
                        <select name="kantor_kas" class="form-select input-highlight fw-bold" required>
                            <option value="">-- Pilih Kantor --</option>
                            <?php foreach ($kas_list as $value => $label): ?>
                                <option value="<?= $value ?>" <?= (isset($_POST['kantor_kas']) && $_POST['kantor_kas'] == $value) ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mt-2"><h6 class="section-title">Profil Calon Nasabah</h6></div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Nama Prospek (Nama/Instansi)</label>
                        <input type="text" name="nama_prospek" class="form-control" placeholder="Contoh: Bpk. Slamet atau Toko Jaya" value="<?= htmlspecialchars($_POST['nama_prospek'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Jenis Prospek</label>
                        <select name="jenis_prospek" class="form-select" required>
                            <option value="">-- Pilih Jenis --</option>
                            <?php
                            try {
                                $q = $conn->query("SELECT * FROM reff_jenis_prospek ORDER BY id ASC");
                                while ($j = $q->fetch()) {
                                    $sel = (isset($_POST['jenis_prospek']) && $_POST['jenis_prospek'] == $j['nama_jenis']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($j['nama_jenis'])."' $sel>".htmlspecialchars($j['nama_jenis'])."</option>";
                                }
                            } catch (PDOException $e) { echo "<option value=''>Gagal memuat</option>"; }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">No HP / WhatsApp</label>
                        <input type="tel" name="no_hp" class="form-control" placeholder="08123xxx" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Pekerjaan / Usaha</label>
                        <input type="text" name="usaha" class="form-control" value="<?= htmlspecialchars($_POST['usaha'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Alamat / Desa</label>
                        <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($_POST['alamat'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kecamatan</label>
                        <input type="text" name="kec" class="form-control" value="<?= htmlspecialchars($_POST['kec'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kabupaten</label>
                        <input type="text" name="kab" class="form-control" value="<?= htmlspecialchars($_POST['kab'] ?? '') ?>" required>
                    </div>

                    <div class="col-12 mt-2"><h6 class="section-title">Potensi & Penawaran</h6></div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estimasi Penempatan Dana (Rp)</label>
                        <input type="number" name="estimasi" class="form-control border-success" placeholder="Masukkan angka saja" value="<?= htmlspecialchars($_POST['estimasi'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Produk Ditawarkan</label>
                        <select name="produk" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php
                            try {
                                $q = $conn->query("SELECT * FROM reff_produk_funding ORDER BY nama_produk ASC");
                                while ($p2 = $q->fetch()) {
                                    $sel = (isset($_POST['produk']) && $_POST['produk'] == $p2['nama_produk']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($p2['nama_produk'])."' $sel>".htmlspecialchars($p2['nama_produk'])."</option>";
                                }
                            } catch (PDOException $e) { echo "<option value=''>Gagal memuat</option>"; }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ketertarikan Nasabah</label>
                        <select name="ketertarikan" class="form-select" required>
                            <option value="">-- Pilih Ketertarikan --</option>
                            <?php
                            try {
                                $q = $conn->query("SELECT * FROM reff_ketertarikan ORDER BY id ASC");
                                while ($k = $q->fetch()) {
                                    $sel = (isset($_POST['ketertarikan']) && $_POST['ketertarikan'] == $k['level_ketertarikan']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($k['level_ketertarikan'])."' $sel>".htmlspecialchars($k['level_ketertarikan'])."</option>";
                                }
                            } catch (PDOException $e) { echo "<option value=''>Gagal memuat</option>"; }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status Prospek (Funnel)</label>
                        <select name="status" class="form-select" required>
                            <option value="">-- Pilih Status --</option>
                            <?php
                            try {
                                $q = $conn->query("SELECT * FROM reff_status ORDER BY id ASC");
                                while ($st = $q->fetch()) {
                                    $sel = (isset($_POST['status']) && $_POST['status'] == $st['nama_status']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($st['nama_status'])."' $sel>".htmlspecialchars($st['nama_status'])."</option>";
                                }
                            } catch (PDOException $e) { echo "<option value=''>Gagal memuat</option>"; }
                            ?>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Hasil Kunjungan</label>
                        <select name="hasil" class="form-select" required>
                            <option value="">-- Pilih Hasil Kunjungan --</option>
                            <?php
                            try {
                                $q = $conn->query("SELECT * FROM reff_hasil_funding ORDER BY id ASC");
                                while ($h = $q->fetch()) {
                                    $sel = (isset($_POST['hasil']) && $_POST['hasil'] == $h['hasil']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($h['hasil'])."' $sel>".htmlspecialchars($h['hasil'])."</option>";
                                }
                            } catch (PDOException $e) { echo "<option value=''>Gagal memuat</option>"; }
                            ?>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Catatan Marketing (Opsional)</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Detail tambahan atau janji temu berikutnya..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mb-4">
                        <label class="form-label"><i class="bi bi-camera-fill me-1"></i> Bukti Kegiatan (Foto, maks 5MB)</label>
                        <input type="file" name="bukti_kegiatan" id="inputFoto" class="form-control" accept="image/*">
                        <img id="fotoPreview" src="" alt="Preview Foto">
                        <span id="compressBadge" class="badge mt-1"></span>
                    </div>
                    <div class="col-12 border-top pt-4 text-center">
                        <button type="reset" class="btn btn-outline-secondary px-4 me-2 rounded-pill" onclick="document.getElementById('fotoPreview').style.display='none'">RESET</button>
                        <button type="submit" class="btn btn-save px-5 shadow-sm rounded-pill" id="btnSimpan">
                            <i class="bi bi-check-circle-fill me-2"></i> SIMPAN PROSPEK FUNDING
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ============================================================
//  Kompresi Gambar otomatis via Canvas
//  - Input maks: 5MB | Output target: ~300KB | Resolusi maks: 1280px
// ============================================================
const COMPRESS_MAX_PX = 1280;
const COMPRESS_TARGET = 300;   // KB
const COMPRESS_MAX_MB = 5;

function compressImage(file) {
    return new Promise((resolve, reject) => {
        if (!file) { resolve({ b64: '', ext: 'jpg' }); return; }
        if (file.size > COMPRESS_MAX_MB * 1024 * 1024) {
            reject(new Error(`Ukuran foto terlalu besar (${(file.size/1024/1024).toFixed(1)}MB). Maks ${COMPRESS_MAX_MB}MB.`));
            return;
        }
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Gagal membaca file foto.'));
        reader.onload = e => {
            const img = new Image();
            img.onerror = () => reject(new Error('File bukan gambar yang valid.'));
            img.onload = () => {
                let { width, height } = img;
                if (width > COMPRESS_MAX_PX || height > COMPRESS_MAX_PX) {
                    if (width > height) { height = Math.round(height * COMPRESS_MAX_PX / width);  width = COMPRESS_MAX_PX; }
                    else                { width  = Math.round(width  * COMPRESS_MAX_PX / height); height = COMPRESS_MAX_PX; }
                }
                const canvas = document.createElement('canvas');
                canvas.width  = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                let quality = 0.85;
                let dataUrl = canvas.toDataURL('image/jpeg', quality);
                while (dataUrl.length * 0.75 > COMPRESS_TARGET * 1024 && quality > 0.2) {
                    quality -= 0.1;
                    dataUrl  = canvas.toDataURL('image/jpeg', quality);
                }
                resolve({ b64: dataUrl.split(',')[1] || '', ext: 'jpg' });
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    //  Notifikasi sukses dari submit normal (tanpa foto)
    // ============================================================
    <?php if ($notif_sukses): ?>
    Swal.fire({ icon:'success', title:'Berhasil!', text:'Data Prospek Funding Berhasil Disimpan!', confirmButtonColor:'#198754' });
    <?php endif; ?>

    // ============================================================
    //  Notifikasi sukses dari submit dengan foto (fetch)
    //  Flag disimpan di sessionStorage sebelum reload()
    // ============================================================
    if (sessionStorage.getItem('funding_sukses') === '1') {
        sessionStorage.removeItem('funding_sukses');
        Swal.fire({ icon:'success', title:'Berhasil!', text:'Data Prospek Funding Berhasil Disimpan!', confirmButtonColor:'#198754' });
    }

    // ============================================================
    //  Notifikasi error dari submit normal (tanpa foto)
    // ============================================================
    <?php if ($status_simpan === "error"): ?>
    Swal.fire({ icon:'error', title:'Gagal Menyimpan!', text:'<?= addslashes($pesan_error) ?>', confirmButtonColor:'#d33' });
    <?php endif; ?>

    // ============================================================
    //  Preview & kompresi otomatis foto
    // ============================================================
    document.getElementById('inputFoto').addEventListener('change', async function () {
        const file  = this.files[0];
        const prev  = document.getElementById('fotoPreview');
        const badge = document.getElementById('compressBadge');

        if (!file) { prev.style.display = 'none'; if (badge) badge.textContent = ''; return; }

        if (file.size > COMPRESS_MAX_MB * 1024 * 1024) {
            Swal.fire({ icon:'warning', title:'File Terlalu Besar', text:`Maks ${COMPRESS_MAX_MB}MB. File ini ${(file.size/1024/1024).toFixed(1)}MB.`, confirmButtonColor:'#ffc107' });
            this.value = '';
            prev.style.display = 'none';
            return;
        }

        // Preview sementara sebelum kompresi selesai
        const origR = new FileReader();
        origR.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; };
        origR.readAsDataURL(file);

        if (badge) { badge.className = 'badge bg-warning text-dark mt-1'; badge.textContent = '⏳ Mengompresi...'; }

        try {
            const { b64 } = await compressImage(file);
            const finalKB = (b64.length * 0.75 / 1024).toFixed(0);
            const origKB  = (file.size / 1024).toFixed(0);
            if (badge) { badge.className = 'badge bg-success mt-1'; badge.textContent = `✅ ${origKB}KB → ${finalKB}KB`; }
            this.dataset.b64 = b64;
            this.dataset.ext = 'jpg';
        } catch(err) {
            if (badge) { badge.className = 'badge bg-danger mt-1'; badge.textContent = '❌ ' + err.message; }
            this.value = '';
            prev.style.display = 'none';
        }
    });

    // ============================================================
    //  Submit Handler
    //  - Ada foto dikompres → fetch + JSON response
    //  - Tidak ada foto     → submit form biasa
    // ============================================================
    document.getElementById('formFunding').addEventListener('submit', async function (e) {
        const fotoInput = document.getElementById('inputFoto');
        const btn       = document.getElementById('btnSimpan');

        if (fotoInput && fotoInput.dataset.b64) {
            // ── Jalur FETCH (ada foto yang sudah dikompres) ──
            e.preventDefault();
            btn.disabled  = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

            const fd = new FormData(this);
            fd.delete('bukti_kegiatan');
            fd.append('foto_b64', fotoInput.dataset.b64);
            fd.append('foto_ext', fotoInput.dataset.ext || 'jpg');

            try {
                const resp = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });

                const json = await resp.json();

                if (json.status === 'sukses') {
                    sessionStorage.setItem('funding_sukses', '1');
                    window.location.reload();
                } else {
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> SIMPAN PROSPEK FUNDING';
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan!',
                        text: json.pesan || 'Terjadi kesalahan pada server.',
                        confirmButtonColor: '#d33'
                    });
                }
            } catch(err) {
                btn.disabled  = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> SIMPAN PROSPEK FUNDING';
                Swal.fire({ icon:'error', title:'Gagal Kirim!', text: err.message, confirmButtonColor:'#d33' });
            }

        } else {
            // ── Jalur NORMAL (tidak ada foto) ──
            btn.disabled  = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
        }
    });
});
</script>
</body>
</html>