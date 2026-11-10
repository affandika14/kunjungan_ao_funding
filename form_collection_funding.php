<?php
session_start();
set_time_limit(300);

// 1. Proteksi Session
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// === PATCH: Paksa PDO error mode ke EXCEPTION agar INSERT gagal tidak diam-diam ===
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) { /* abaikan jika sudah diset di koneksi.php */ }

$id_pegawai   = $_SESSION['pegawai_id'];
$nama_display = $_SESSION['nama_user'] ?? "Guest";

// Tentukan link "kembali" sesuai role (atasan kembali ke dashboard atasan)
$link_kembali = ($_SESSION['role'] ?? '') === 'atasan' ? 'dashboard_atasan.php' : 'dashboard_form_kegiatan.php';

$status_simpan = "";
$pesan_error   = "";

// ============================================================
//  HANDLE AJAX SUBMIT (dari JavaScript fetch per-batch)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_batch'])) {
    header('Content-Type: application/json');
    try {
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // --- Mapping Kepala Kas ---
        $kantor_kas  = $_POST['kantor_kas'] ?? '';
        $kas         = strtolower($kantor_kas);
        $nama_kepala = "";

        if      (strpos($kas, "sumoroto")   !== false) $nama_kepala = "WANDA MIFTAKHUL JANNAH";
        elseif  (strpos($kas, "ngariboyo")  !== false || strpos($kas, "parang")      !== false) $nama_kepala = "VITRI DWI CAHYANI";
        elseif  (strpos($kas, "dolopo")     !== false || strpos($kas, "jiwan")       !== false) $nama_kepala = "Asrul Anggriawan Azis";
        elseif  (strpos($kas, "rejoso")     !== false) $nama_kepala = "agus sunaryo";
        elseif  (strpos($kas, "takeran")    !== false || strpos($kas, "lembeyan")    !== false) $nama_kepala = "PIGGY MEI AYYUDHI";
        elseif  (strpos($kas, "bendo")      !== false || strpos($kas, "maospati")    !== false) $nama_kepala = "SUPRAPTO";
        elseif  (strpos($kas, "karas")      !== false || strpos($kas, "karangrejo")  !== false || strpos($kas, "barat") !== false) $nama_kepala = "ANDIKA DEVI RISMIANTO";
        elseif  (strpos($kas, "panekan")    !== false || strpos($kas, "magetan")     !== false) $nama_kepala = "ZEQI IRAWAN";

        $id_kepala_kas = null;
        if ($nama_kepala !== "") {
            $stmt_id = $conn->prepare("SELECT id FROM pegawai WHERE nama LIKE ? LIMIT 1");
            $stmt_id->execute(["%$nama_kepala%"]);
            $kepala = $stmt_id->fetch();
            if ($kepala) $id_kepala_kas = $kepala['id'];
        }

        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $rows    = json_decode($_POST['rows_json'], true);

        if (!is_array($rows) || empty($rows)) {
            echo json_encode(['ok' => false, 'msg' => 'Data baris kosong atau tidak valid.']);
            exit;
        }

        $conn->beginTransaction();

        $sql = "INSERT INTO trx_collection_funding 
                (pegawai_id, id_kepala_kas_lokasi, tanggal, wilayah_resort, desa_lokasi, kecamatan, kabupaten_kota,
                 nama_nasabah, produk_nasabah, status_nasabah, saldo_sebelum, setoran_hari_ini, saldo_setelah,
                 metode_setoran, frekuensi_setoran, potensi_peningkatan, produk_lain_ditawarkan, kendala_lapangan,
                 catatan, bukti_kegiatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $conn->prepare($sql);

        foreach ($rows as $row) {
            $nama_nasabah = trim($row['nasabah'] ?? '');
            if ($nama_nasabah === '') continue;

            // Handle foto — dikirim sebagai base64 per baris
            $nama_file = "";
            if (!empty($row['foto_base64']) && !empty($row['foto_ext'])) {
                $base64data = $row['foto_base64'];
                // Validasi ukuran (base64 ~4/3 × ukuran asli, 2MB → ~2.7MB base64)
                if (strlen($base64data) > 700000) { // ~500KB setelah kompresi JS
                    throw new Exception("Foto nasabah '{$nama_nasabah}' masih terlalu besar setelah kompresi. Gunakan foto yang lebih kecil.");
                }
                $ext       = preg_replace('/[^a-z0-9]/', '', strtolower($row['foto_ext']));
                $nama_file = "COLL_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
                $target    = "uploads/" . $nama_file;
                if (!is_dir("uploads/")) mkdir("uploads/", 0777, true);
                $decoded = base64_decode($base64data);
                if ($decoded === false || file_put_contents($target, $decoded) === false) {
                    throw new Exception("Gagal menyimpan foto nasabah '{$nama_nasabah}'.");
                }
            }

            $s_awal  = ($row['saldo_awal']  !== '') ? $row['saldo_awal']  : null;
            $s_setor = ($row['setoran']      !== '') ? $row['setoran']      : null;
            $s_akhir = ($row['saldo_akhir']  !== '') ? $row['saldo_akhir']  : null;

            $stmt_ins->execute([
                $id_pegawai, $id_kepala_kas, $tanggal,
                $row['wilayah']      ?? '',
                $row['desa']         ?? '',
                $row['kec']          ?? '',
                $row['kab']          ?? '',
                $nama_nasabah,
                $row['produk']       ?? '',
                $row['status']       ?? '',
                $s_awal, $s_setor, $s_akhir,
                $row['metode']       ?? '',
                $row['frekuensi']    ?? '',
                $row['potensi']      ?? '',
                $row['produk_lain']  ?? '-',
                $row['kendala']      ?? '',
                $row['catatan']      ?? '',
                $nama_file
            ]);
        }

        $conn->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("[COLLECTION AJAX ERROR] " . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// Cek notifikasi sukses dari session (redirect biasa)
$notif_sukses = false;
if (isset($_SESSION['status_simpan'])) {
    $notif_sukses = true;
    unset($_SESSION['status_simpan']);
}

// Ambil referensi untuk dropdown (tetap via PHP karena hanya render awal)
try {
    $ref_produk   = $conn->query("SELECT nama_produk FROM reff_produk_funding ORDER BY nama_produk ASC")->fetchAll(PDO::FETCH_COLUMN);
    $ref_status   = $conn->query("SELECT nama FROM reff_status_nasabah ORDER BY nama ASC")->fetchAll(PDO::FETCH_COLUMN);
    $ref_metode   = $conn->query("SELECT nama FROM reff_metode_setoran")->fetchAll(PDO::FETCH_COLUMN);
    $ref_frekuensi= $conn->query("SELECT nama FROM reff_frekuensi_setoran")->fetchAll(PDO::FETCH_COLUMN);
    $ref_potensi  = $conn->query("SELECT nama FROM reff_potensi")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $ref_produk = $ref_status = $ref_metode = $ref_frekuensi = $ref_potensi = [];
}

// Helper: buat <option> dari array
function makeOptions(array $list, string $selected = ''): string {
    $out = '';
    foreach ($list as $v) {
        $sel  = ($v === $selected) ? ' selected' : '';
        $v_e  = htmlspecialchars($v);
        $out .= "<option value=\"{$v_e}\"{$sel}>{$v_e}</option>";
    }
    return $out;
}

$kas_list = [
    "KCU"        => "Kantor Cabang Utama",
    "Madiun"     => "Cabang Madiun",
    "Ngawi"      => "Cabang Ngawi",
    "Ponorogo"   => "Cabang Ponorogo",
    "Trenggalek" => "Cabang Trenggalek",
    "Nganjuk"    => "Cabang Nganjuk",
    "Magetan"    => "Kantor Kas Magetan",
    "Sumoroto"   => "Kantor Kas Sumoroto",
    "Ngariboyo"  => "Kantor Kas Ngariboyo",
    "Parang"     => "Kantor Kas Parang",
    "Dolopo"     => "Kantor Kas Dolopo",
    "Jiwan"      => "Kantor Kas Jiwan",
    "Rejoso"     => "Kantor Kas Rejoso",
    "Takeran"    => "Kantor Kas Takeran",
    "Lembeyan"   => "Kantor Kas Lembeyan",
    "Bendo"      => "Kantor Kas Bendo",
    "Maospati"   => "Kantor Kas Maospati",
    "Karas"      => "Kantor Kas Karas",
    "Karangrejo" => "Kantor Kas Karangrejo",
    "Barat"      => "Kantor Kas Barat",
    "Panekan"    => "Kantor Kas Panekan",
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Bulk Collection - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .form-label { font-weight: 700; color: #146c43; font-size: 0.75rem; text-transform: uppercase; }
        .row-nasabah { border: 1px solid #dee2e6; padding: 30px 20px 20px 20px; border-radius: 15px; margin-bottom: 25px; position: relative; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .data-number { position: absolute; top: 0; left: 20px; background: #198754; color: white; padding: 2px 15px; border-radius: 0 0 10px 10px; font-weight: bold; font-size: 0.8rem; }
        .btn-remove { position: absolute; top: -10px; right: -10px; z-index: 10; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .sticky-bottom-bar { position: sticky; bottom: 0; background: white; padding: 15px; box-shadow: 0 -5px 15px rgba(0,0,0,0.1); z-index: 100; border-top: 2px solid #198754; }
        .saldo-highlight { background-color: #e9f5ee; border-left: 4px solid #198754; }
        .input-kas { background-color: #fff3cd; border: 2px solid #ffc107; }
        .foto-preview { max-height: 60px; border-radius: 6px; margin-top: 5px; display: none; }
        #progressBar { transition: width .3s; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand small fw-bold" href="<?= $link_kembali ?>"><i class="bi bi-arrow-left-circle me-1"></i> KEMBALI</a>
        <span class="navbar-text text-white">PETUGAS: <strong><?= htmlspecialchars($nama_display) ?></strong></span>
    </div>
</nav>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-success mb-0"><i class="bi bi-layers-half me-2"></i>INPUT KOLEKSI MASSAL</h5>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="autoCopy" checked>
            <label class="form-check-label small fw-bold text-success" for="autoCopy">SAMAKAN ISIAN WILAYAH</label>
        </div>
    </div>

    <!-- Header: Tanggal & Kantor Kas -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-white rounded-3 border-start border-4 border-warning">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Tanggal Transaksi</label>
                    <input type="date" id="inp_tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-9 mb-2">
                    <label class="form-label text-danger">Kantor Kas Wilayah Transaksi</label>
                    <select id="inp_kantor_kas" class="form-select input-kas fw-bold" required>
                        <option value="">-- Pilih Kantor --</option>
                        <?php foreach ($kas_list as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Container Baris Nasabah -->
    <div id="container-nasabah"></div>

    <div class="sticky-bottom-bar rounded-pill d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-outline-success fw-bold rounded-pill px-4" onclick="tambahBaris()">
            <i class="bi bi-plus-circle me-1"></i> TAMBAH NASABAH
        </button>
        <div class="d-flex align-items-center gap-3">
            <span id="counter-info" class="small text-muted fw-bold"></span>
            <button type="button" class="btn btn-success px-5 py-2 fw-bold shadow rounded-pill" id="btnSimpan" onclick="submitSemua()">
                <i class="bi bi-cloud-upload-fill me-2"></i> SIMPAN SEMUA DATA
            </button>
        </div>
    </div>

    <!-- Progress bar simpan -->
    <div id="progress-area" class="mt-3" style="display:none;">
        <div class="progress" style="height:20px;">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%"></div>
        </div>
        <p id="progress-text" class="text-center small text-muted mt-1"></p>
    </div>
</div>

<!-- Template baris nasabah (hidden) -->
<template id="tpl-baris">
    <div class="row-nasabah">
        <div class="data-number">NASABAH #1</div>
        <button type="button" class="btn btn-danger btn-sm btn-remove rounded-circle shadow" onclick="hapusBaris(this)">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label text-primary">Wilayah / Resort</label>
                <input type="text" data-field="wilayah" class="form-control f-wilayah" placeholder="Resort.." required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-primary">Desa / Lokasi</label>
                <input type="text" data-field="desa" class="form-control f-desa" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-primary">Kecamatan</label>
                <input type="text" data-field="kec" class="form-control f-kec" required>
            </div>
            <div class="col-md-3">
                <label class="form-label text-primary">Kabupaten</label>
                <input type="text" data-field="kab" class="form-control f-kab" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Nama Lengkap Nasabah</label>
                <input type="text" data-field="nasabah" class="form-control fw-bold border-success" placeholder="Nama Nasabah.." required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Produk Funding</label>
                <select data-field="produk" class="form-select" required>
                    <option value="">-- Pilih Produk --</option>
                    <?= makeOptions($ref_produk) ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status Nasabah</label>
                <select data-field="status" class="form-select" required>
                    <option value="">-- Pilih Status --</option>
                    <?= makeOptions($ref_status) ?>
                </select>
            </div>
            <div class="col-12">
                <div class="p-3 rounded-3 saldo-highlight">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label text-muted">Saldo Sebelum (Rp)</label>
                            <input type="number" data-field="saldo_awal" class="form-control s-awal">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-success">Setoran Hari Ini (Rp)</label>
                            <input type="number" data-field="setoran" class="form-control s-setoran fw-bold">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-primary">Saldo Setelah (Rp)</label>
                            <input type="number" data-field="saldo_akhir" class="form-control s-akhir bg-white" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Metode Setoran</label>
                <select data-field="metode" class="form-select">
                    <?= makeOptions($ref_metode) ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Frekuensi Setoran</label>
                <select data-field="frekuensi" class="form-select">
                    <?= makeOptions($ref_frekuensi) ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Potensi Peningkatan</label>
                <select data-field="potensi" class="form-select">
                    <?= makeOptions($ref_potensi) ?>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label small text-muted">Cross-Selling (Produk Lain)</label>
                <select data-field="produk_lain" class="form-select">
                    <option value="-">Tidak Ada</option>
                    <?= makeOptions($ref_produk) ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Kendala di Lapangan</label>
                <textarea data-field="kendala" class="form-control" rows="1"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Catatan Marketing</label>
                <textarea data-field="catatan" class="form-control" rows="1"></textarea>
            </div>
            <div class="col-12">
                <label class="form-label"><i class="bi bi-camera me-1"></i> Foto Bukti (maks 5MB)</label>
                <input type="file" data-field="foto" class="form-control input-foto" accept="image/*">
                <img class="foto-preview" src="" alt="preview">
            </div>
        </div>
    </div>
</template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================================
//  Notifikasi dari session (redirect biasa)
// ============================================================
<?php if ($notif_sukses): ?>
Swal.fire({ icon:'success', title:'Berhasil!', text:'Data Koleksi Berhasil Disimpan!', confirmButtonColor:'#198754' });
<?php endif; ?>

// ============================================================
//  Manajemen Baris
// ============================================================
let barисCount = 0;

function tambahBaris(prefillWilayah) {
    const tpl  = document.getElementById('tpl-baris');
    const clone = tpl.content.cloneNode(true);
    const div   = clone.querySelector('.row-nasabah');

    barисCount++;
    div.querySelector('.data-number').textContent = 'NASABAH #' + barисCount;
    div.dataset.idx = barисCount;

    // Samakan wilayah dari baris pertama jika toggle aktif
    if (document.getElementById('autoCopy').checked && barисCount > 1) {
        const first = document.querySelector('.row-nasabah');
        if (first) {
            div.querySelector('.f-wilayah').value = first.querySelector('.f-wilayah').value;
            div.querySelector('.f-desa').value     = first.querySelector('.f-desa').value;
            div.querySelector('.f-kec').value      = first.querySelector('.f-kec').value;
            div.querySelector('.f-kab').value      = first.querySelector('.f-kab').value;
        }
    }
    if (prefillWilayah) {
        div.querySelector('.f-wilayah').value = prefillWilayah.wilayah || '';
        div.querySelector('.f-desa').value     = prefillWilayah.desa   || '';
        div.querySelector('.f-kec').value      = prefillWilayah.kec    || '';
        div.querySelector('.f-kab').value      = prefillWilayah.kab    || '';
    }

    // Sembunyikan tombol hapus di baris pertama
    if (barисCount === 1) div.querySelector('.btn-remove').style.display = 'none';

    // Auto-hitung saldo
    bindSaldoCalc(div);

    // Preview foto + kompresi otomatis (maks input 5MB, output ~300KB)
    div.querySelector('.input-foto').addEventListener('change', async function () {
        const file    = this.files[0];
        const col     = this.closest('.col-12');
        const preview = col.querySelector('.foto-preview');
        let   badge   = col.querySelector('.compress-badge');

        if (!file) { preview.style.display = 'none'; if (badge) badge.remove(); return; }

        // Tolak > 5MB
        if (file.size > COMPRESS_MAX_MB * 1024 * 1024) {
            Swal.fire({ icon:'warning', title:'File Terlalu Besar', text:`Ukuran foto maks ${COMPRESS_MAX_MB}MB. File ini ${(file.size/1024/1024).toFixed(1)}MB.`, confirmButtonColor:'#ffc107' });
            this.value = '';
            preview.style.display = 'none';
            if (badge) badge.remove();
            return;
        }

        // Preview gambar asli sementara
        const origReader = new FileReader();
        origReader.onload = ev => { preview.src = ev.target.result; preview.style.display = 'block'; };
        origReader.readAsDataURL(file);

        // Badge status kompresi
        if (!badge) { badge = document.createElement('span'); badge.className = 'compress-badge badge bg-warning text-dark mt-1 d-inline-block'; col.appendChild(badge); }
        badge.textContent = '⏳ Mengompresi...';

        try {
            const { b64 } = await compressImage(file);
            const finalKB = (b64.length * 0.75 / 1024).toFixed(0);
            const origKB  = (file.size / 1024).toFixed(0);
            badge.className = 'compress-badge badge bg-success mt-1 d-inline-block';
            badge.textContent = `✅ ${origKB}KB → ${finalKB}KB`;
            // Simpan hasil kompresi di dataset, dipakai saat submit
            this.dataset.b64 = b64;
            this.dataset.ext = 'jpg';
        } catch (err) {
            badge.className = 'compress-badge badge bg-danger mt-1 d-inline-block';
            badge.textContent = '❌ ' + err.message;
            this.value = '';
            preview.style.display = 'none';
        }
    });

    document.getElementById('container-nasabah').appendChild(div);
    updateCounter();
}

function hapusBaris(btn) {
    btn.closest('.row-nasabah').remove();
    updateNomor();
    updateCounter();
}

function updateNomor() {
    document.querySelectorAll('.row-nasabah').forEach((row, i) => {
        row.querySelector('.data-number').textContent = 'NASABAH #' + (i + 1);
    });
    barисCount = document.querySelectorAll('.row-nasabah').length;
}

function updateCounter() {
    const n = document.querySelectorAll('.row-nasabah').length;
    document.getElementById('counter-info').textContent = n + ' nasabah';
}

function bindSaldoCalc(div) {
    const sAwal   = div.querySelector('.s-awal');
    const sSetor  = div.querySelector('.s-setoran');
    const sAkhir  = div.querySelector('.s-akhir');
    const calc = () => {
        const a = parseFloat(sAwal.value)  || 0;
        const s = parseFloat(sSetor.value) || 0;
        sAkhir.value = (sAwal.value === '' && sSetor.value === '') ? '' : (a + s);
    };
    sAwal.addEventListener('input', calc);
    sSetor.addEventListener('input', calc);
}

// ============================================================
//  Baca nilai satu baris → object
// ============================================================
function getRowData(row) {
    const g = f => {
        const el = row.querySelector(`[data-field="${f}"]`);
        if (!el) return '';
        return (el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') ? el.value : el.value;
    };
    return {
        wilayah:     g('wilayah'),
        desa:        g('desa'),
        kec:         g('kec'),
        kab:         g('kab'),
        nasabah:     g('nasabah'),
        produk:      g('produk'),
        status:      g('status'),
        saldo_awal:  g('saldo_awal'),
        setoran:     g('setoran'),
        saldo_akhir: g('saldo_akhir'),
        metode:      g('metode'),
        frekuensi:   g('frekuensi'),
        potensi:     g('potensi'),
        produk_lain: g('produk_lain'),
        kendala:     g('kendala'),
        catatan:     g('catatan'),
        foto_base64: '',
        foto_ext:    '',
    };
}

// ============================================================
//  Kompresi Gambar via Canvas → base64 JPEG
//  - Maks ukuran input: 5MB
//  - Output: JPEG kualitas turun bertahap sampai < TARGET_KB
//  - Resolusi maks: 1280px (lebar/tinggi)
// ============================================================
const COMPRESS_MAX_PX  = 1280;   // resolusi maks (px)
const COMPRESS_TARGET  = 300;    // target ukuran output (KB)
const COMPRESS_MAX_MB  = 5;      // batas upload dari user (MB)

function compressImage(file) {
    return new Promise((resolve, reject) => {
        if (!file) { resolve({ b64: '', ext: 'jpg' }); return; }

        // Tolak file > 5MB sebelum diproses
        if (file.size > COMPRESS_MAX_MB * 1024 * 1024) {
            reject(new Error(`Ukuran foto terlalu besar (${(file.size/1024/1024).toFixed(1)}MB). Maksimal ${COMPRESS_MAX_MB}MB.`));
            return;
        }

        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Gagal membaca file foto.'));
        reader.onload = e => {
            const img = new Image();
            img.onerror = () => reject(new Error('File bukan gambar yang valid.'));
            img.onload = () => {
                // Hitung dimensi baru (jaga rasio, maks COMPRESS_MAX_PX)
                let { width, height } = img;
                if (width > COMPRESS_MAX_PX || height > COMPRESS_MAX_PX) {
                    if (width > height) { height = Math.round(height * COMPRESS_MAX_PX / width);  width = COMPRESS_MAX_PX; }
                    else                { width  = Math.round(width  * COMPRESS_MAX_PX / height); height = COMPRESS_MAX_PX; }
                }

                const canvas = document.createElement('canvas');
                canvas.width  = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);

                // Turunkan kualitas bertahap sampai ukuran di bawah target
                let quality = 0.85;
                let dataUrl = canvas.toDataURL('image/jpeg', quality);
                while (dataUrl.length * 0.75 > COMPRESS_TARGET * 1024 && quality > 0.2) {
                    quality  -= 0.1;
                    dataUrl   = canvas.toDataURL('image/jpeg', quality);
                }

                const b64 = dataUrl.split(',')[1] || '';
                const origKB  = (file.size / 1024).toFixed(0);
                const finalKB = (b64.length * 0.75 / 1024).toFixed(0);
                console.log(`[Foto] ${file.name}: ${origKB}KB → ${finalKB}KB (q=${quality.toFixed(1)})`);
                resolve({ b64, ext: 'jpg' });
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// ============================================================
//  Submit: kumpulkan semua data, kirim per-batch via fetch
// ============================================================
async function submitSemua() {
    const tanggal    = document.getElementById('inp_tanggal').value;
    const kantorKas  = document.getElementById('inp_kantor_kas').value;

    if (!tanggal)   { Swal.fire({ icon:'warning', title:'Tanggal wajib diisi!', confirmButtonColor:'#ffc107' }); return; }
    if (!kantorKas) { Swal.fire({ icon:'warning', title:'Pilih Kantor Kas terlebih dahulu!', confirmButtonColor:'#ffc107' }); return; }

    const rows = document.querySelectorAll('.row-nasabah');
    if (rows.length === 0) { Swal.fire({ icon:'warning', title:'Belum ada data nasabah!', confirmButtonColor:'#ffc107' }); return; }

    // Validasi nasabah wajib
    let valid = true;
    rows.forEach(row => {
        const nama = row.querySelector('[data-field="nasabah"]').value.trim();
        if (!nama) { row.querySelector('[data-field="nasabah"]').classList.add('is-invalid'); valid = false; }
        else row.querySelector('[data-field="nasabah"]').classList.remove('is-invalid');
    });
    if (!valid) { Swal.fire({ icon:'error', title:'Ada Nama Nasabah yang Kosong!', text:'Isi semua nama nasabah atau hapus baris yang tidak diperlukan.', confirmButtonColor:'#d33' }); return; }

    // Kumpulkan semua data — foto sudah dikompres saat dipilih (dataset.b64)
    const allRows = [];
    for (const row of rows) {
        const data      = getRowData(row);
        const fileInput = row.querySelector('.input-foto');
        if (fileInput && fileInput.dataset.b64) {
            // Gunakan hasil kompresi yang sudah ada di dataset
            data.foto_base64 = fileInput.dataset.b64;
            data.foto_ext    = fileInput.dataset.ext || 'jpg';
        } else if (fileInput && fileInput.files[0]) {
            // Fallback: kompresi saat submit jika belum ada di dataset
            try {
                const { b64 } = await compressImage(fileInput.files[0]);
                data.foto_base64 = b64;
                data.foto_ext    = 'jpg';
            } catch(e) {
                Swal.fire({ icon:'error', title:'Gagal kompresi foto', text: e.message, confirmButtonColor:'#d33' });
                return;
            }
        }
        allRows.push(data);
    }

    // Nonaktifkan tombol simpan
    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
    document.getElementById('progress-area').style.display = 'block';

    // ============================================================
    //  Kirim per-batch 20 baris agar tidak timeout / size limit
    // ============================================================
    const BATCH_SIZE = 20;
    let saved = 0;

    for (let i = 0; i < allRows.length; i += BATCH_SIZE) {
        const batch = allRows.slice(i, i + BATCH_SIZE);
        const pct   = Math.round(((i + batch.length) / allRows.length) * 100);

        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progress-text').textContent =
            `Menyimpan ${i + 1}–${Math.min(i + BATCH_SIZE, allRows.length)} dari ${allRows.length} nasabah...`;

        const fd = new FormData();
        fd.append('ajax_batch',  '1');
        fd.append('tanggal',     tanggal);
        fd.append('kantor_kas',  kantorKas);
        fd.append('rows_json',   JSON.stringify(batch));

        try {
            const resp = await fetch(window.location.href, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                throw new Error(json.msg || 'Server error');
            }
            saved += batch.length;
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-upload-fill me-2"></i> SIMPAN SEMUA DATA';
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan Batch!',
                html: `<b>Batch ${Math.floor(i/BATCH_SIZE)+1} gagal.</b><br>${err.message}<br><small class="text-danger">Data sebelum batch ini sudah tersimpan (${saved} dari ${allRows.length}).</small>`,
                confirmButtonColor: '#d33'
            });
            return;
        }
    }

    // Semua berhasil
    document.getElementById('progressBar').style.width = '100%';
    document.getElementById('progress-text').textContent = 'Selesai!';
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: `${saved} data koleksi berhasil disimpan!`,
        confirmButtonColor: '#198754'
    }).then(() => window.location.reload());
}

// Init: buat 1 baris pertama saat halaman load
document.addEventListener('DOMContentLoaded', () => tambahBaris());
</script>
</body>
</html>