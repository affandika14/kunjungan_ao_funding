<?php
include 'koneksi.php';

try { $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Exception $e) {}

// ============================================================
//  AJAX ENDPOINT — cek nama real-time
// ============================================================
if (isset($_GET['cek_nama'])) {
    header('Content-Type: application/json');
    $nama_cek = trim($_GET['cek_nama'] ?? '');
    if ($nama_cek === '') { echo json_encode(['ada' => false, 'mirip' => []]); exit; }

    // 1. Exact match
    $stmt_exact = $conn->prepare(
        "SELECT COUNT(*) FROM pegawai WHERE LOWER(nama) = LOWER(?)"
    );
    $stmt_exact->execute([$nama_cek]);
    $jumlah_exact = (int) $stmt_exact->fetchColumn();

    if ($jumlah_exact > 0) {
        echo json_encode(['ada' => true, 'mirip' => []]);
        exit;
    }

    // 2. Partial match — nama input ada di dalam nama yang sudah terdaftar
    $stmt_mirip = $conn->prepare(
        "SELECT nama FROM pegawai WHERE LOWER(nama) LIKE LOWER(?) AND LOWER(nama) != LOWER(?) ORDER BY nama ASC LIMIT 5"
    );
    $stmt_mirip->execute(['%' . $nama_cek . '%', $nama_cek]);
    $nama_mirip = $stmt_mirip->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['ada' => false, 'mirip' => $nama_mirip]);
    exit;
}

// ============================================================
//  PROSES FORM SUBMIT
// ============================================================
$pesan = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $nama    = trim($_POST['nama']    ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $kantor  = trim($_POST['kantor']  ?? '');
        $pin     = trim($_POST['pin']     ?? '');

        // ── Validasi awal ────────────────────────────────────
        if ($nama === '' || $jabatan === '' || $kantor === '' || $pin === '') {
            throw new Exception("Semua field wajib diisi.");
        }
        if (!preg_match('/^\d{5}$/', $pin)) {
            throw new Exception("PIN harus tepat 5 digit angka.");
        }

        // ── CEK DUPLIKASI NAMA (case-insensitive) ─────────────
        $stmt_dup = $conn->prepare(
            "SELECT id FROM pegawai WHERE LOWER(nama) = LOWER(?)"
        );
        $stmt_dup->execute([$nama]);
        if ($stmt_dup->fetch()) {
            throw new Exception(
                "Nama <strong>" . htmlspecialchars($nama) . "</strong> sudah terdaftar di sistem. " .
                "Gunakan nama lengkap sesuai SK, atau hubungi admin jika terjadi kesalahan."
            );
        }

        // ── Juga cek di tabel users (username = nama) ─────────
        $stmt_usr = $conn->prepare(
            "SELECT id FROM users WHERE LOWER(username) = LOWER(?)"
        );
        $stmt_usr->execute([$nama]);
        if ($stmt_usr->fetch()) {
            throw new Exception(
                "Username <strong>" . htmlspecialchars($nama) . "</strong> sudah digunakan. " .
                "Hubungi admin untuk bantuan."
            );
        }

        // ── Semua OK → simpan dengan transaksi ────────────────
        $conn->beginTransaction();

        // 1. Simpan ke tabel PEGAWAI
        $stmt_p = $conn->prepare("INSERT INTO pegawai (nama, jabatan, kantor) VALUES (?, ?, ?)");
        $stmt_p->execute([$nama, $jabatan, $kantor]);
        $id_baru = $conn->lastInsertId();

        // 2. Simpan ke tabel USERS
        $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
        $stmt_u = $conn->prepare("INSERT INTO users (pegawai_id, username, password) VALUES (?, ?, ?)");
        $stmt_u->execute([$id_baru, $nama, $hashed_pin]);

        // 3. Mapping Atasan
        $mappings = [];
        if (!empty($_POST['id_kepala_kas']))    $mappings[] = ['id' => $_POST['id_kepala_kas'],    'level' => 'Kas'];
        if (!empty($_POST['id_kepala_bagian'])) $mappings[] = ['id' => $_POST['id_kepala_bagian'], 'level' => 'Bagian'];
        if (!empty($_POST['id_kepala_cabang'])) {
            $id_kacab = $_POST['id_kepala_cabang'];
            $mappings[] = ['id' => $id_kacab, 'level' => 'Cabang'];
            // Logika khusus: Rian Dian Raga (ID 21) → Sony Wahyu S. (ID 40) otomatis
            if ($id_kacab == 21) {
                $mappings[] = ['id' => 40, 'level' => 'Wakil'];
            }
        }

        if (!empty($mappings)) {
            $stmt_map = $conn->prepare(
                "INSERT INTO mapping_atasan (id_atasan, id_bawahan, level_atasan) VALUES (?, ?, ?)"
            );
            foreach ($mappings as $m) {
                $stmt_map->execute([$m['id'], $id_baru, $m['level']]);
            }
        }

        $conn->commit();
        echo "<script>alert('Pendaftaran Berhasil! Silakan Login menggunakan Nama dan PIN Anda.'); window.location='login.php';</script>";
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $pesan = "<div class='alert alert-danger shadow-sm rounded-3'>
                    <i class='bi bi-x-circle-fill me-2'></i>" . $e->getMessage() .
                  "</div>";
    }
}

// ── Dropdown atasan ──────────────────────────────────────────
$list_kas   = $conn->query("SELECT id, nama FROM pegawai WHERE jabatan LIKE '%Kas%' ORDER BY nama ASC")->fetchAll();
$list_kabag = $conn->query("SELECT id, nama FROM pegawai WHERE jabatan LIKE '%Bagian%' ORDER BY nama ASC")->fetchAll();
$list_kacab = $conn->query("SELECT id, nama FROM pegawai WHERE jabatan LIKE '%Cabang%' AND id != 40 ORDER BY nama ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Petugas Baru - BPR Ekadharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .card-regis { border: none; border-radius: 20px; box-shadow: 0 10px 40px rgba(25,135,84,0.15); overflow: hidden; }
        .header-green { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); color: white; padding: 30px; }
        .form-label { font-weight: 700; color: #146c43; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 5px; }
        .section-title { font-size: 0.8rem; font-weight: 800; color: #198754; border-bottom: 2px solid #e9f5ee; padding-bottom: 5px; margin-bottom: 15px; margin-top: 10px; }
        .form-control, .form-select { border-radius: 10px; padding: 10px 15px; border: 1px solid #ced4da; font-size: 14px; }
        .btn-regis { background-color: #198754; border: none; color: white; padding: 15px; font-weight: 700; border-radius: 12px; transition: 0.3s; }
        .btn-regis:hover { background-color: #0d5a36; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(25,135,84,0.3); }
        .btn-regis:disabled { background-color: #6c757d; transform: none; box-shadow: none; cursor: not-allowed; }

        /* Status nama */
        #statusNama { font-size: 0.78rem; margin-top: 5px; min-height: 18px; }
        #inputNama.is-valid   { border-color: #198754; background-image: none; }
        #inputNama.is-invalid { border-color: #dc3545; background-image: none; }
        #inputNama.is-warning { border-color: #ffc107; background-image: none; }
    </style>
</head>
<body class="py-4">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card card-regis shadow-lg">
                <div class="header-green text-center">
                    <i class="bi bi-person-plus display-5 mb-2"></i>
                    <h4 class="fw-bold mb-0">PENDAFTARAN PETUGAS</h4>
                    <small class="opacity-75">BPR Ekadharma - Divisi Marketing</small>
                </div>
                <div class="card-body p-4 p-md-5 bg-white">

                    <?= $pesan ?>

                    <form method="POST" id="formRegis">

                        <div class="section-title"><i class="bi bi-person-badge me-2"></i>DATA PRIBADI</div>

                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap (Sesuai SK)</label>
                            <div class="input-group">
                                <input type="text" name="nama" id="inputNama"
                                    class="form-control"
                                    placeholder="CONTOH: BUDI SANTOSO"
                                    value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                                    autocomplete="off"
                                    required>
                                <span class="input-group-text bg-white border-start-0" id="ikonNama">
                                    <i class="bi bi-person text-muted" id="iconNamaInner"></i>
                                </span>
                            </div>
                            <div id="statusNama"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jabatan Anda</label>
                                <select name="jabatan" class="form-select" required>
                                    <option value="">-- Pilih --</option>
                                    <option value="Staff AO"        <?= (($_POST['jabatan'] ?? '') == 'Staff AO')        ? 'selected' : '' ?>>Staff AO</option>
                                    <option value="Staff Funding"   <?= (($_POST['jabatan'] ?? '') == 'Staff Funding')   ? 'selected' : '' ?>>Staff Funding</option>
                                    <option value="Staff Penagihan" <?= (($_POST['jabatan'] ?? '') == 'Staff Penagihan') ? 'selected' : '' ?>>Staff Penagihan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Kerja</label>
                                <select name="kantor" class="form-select" required> 
                                    <option value="">-- Pilih --</option>
                                    <option value="Kantor Cabang Utama">Kantor Cabang Utama</option>
                                    <option value="Kantor Cabang Nganjuk">Kantor Cabang Nganjuk</option>
                                    <option value="Kantor Cabang Madiun">Kantor Cabang Madiun</option>
                                    <option value="Kantor Cabang Ponorogo">Kantor Cabang Ponorogo</option>
                                    <option value="Kantor Cabang Trenggalek">Kantor Cabang Trenggalek</option>
                                    <option value="Kantor Cabang Ngawi">Kantor Cabang Ngawi</option>
                                </select>
                                <!-- <input type="text" name="kantor" class="form-control"
                                    placeholder="Cabang/Kantor Kas"
                                    value="<?= htmlspecialchars($_POST['kantor'] ?? '') ?>"
                                    required> -->
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-diagram-3 me-2"></i>STRUKTUR ATASAN (PUK)</div>
                        <p class="text-muted mb-3" style="font-size:11px;">Pilih atasan yang berwenang memantau laporan Anda. Kosongkan jika tidak ada.</p>

                        <div class="mb-3">
                            <label class="form-label text-muted small">1. Kepala Kantor Kas</label>
                            <select name="id_kepala_kas" class="form-select">
                                <option value="">-- Tidak Ada Kepala Kas --</option>
                                <?php foreach ($list_kas as $at): ?>
                                    <option value="<?= $at['id'] ?>"
                                        <?= (isset($_POST['id_kepala_kas']) && $_POST['id_kepala_kas'] == $at['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($at['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small">2. Kepala Bagian</label>
                            <select name="id_kepala_bagian" class="form-select">
                                <option value="">-- Tidak Ada Kepala Bagian --</option>
                                <?php foreach ($list_kabag as $at): ?>
                                    <option value="<?= $at['id'] ?>"
                                        <?= (isset($_POST['id_kepala_bagian']) && $_POST['id_kepala_bagian'] == $at['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($at['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small">3. Kepala Cabang</label>
                            <select name="id_kepala_cabang" class="form-select">
                                <option value="">-- Tidak Ada Kepala Cabang --</option>
                                <?php foreach ($list_kacab as $at): ?>
                                    <option value="<?= $at['id'] ?>"
                                        <?= (isset($_POST['id_kepala_cabang']) && $_POST['id_kepala_cabang'] == $at['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($at['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="section-title"><i class="bi bi-key me-2"></i>KEAMANAN AKUN</div>

                        <div class="mb-4">
                            <label class="form-label text-danger">BUAT PIN LOGIN (5 DIGIT)</label>
                            <input type="password" name="pin" class="form-control fw-bold text-center"
                                maxlength="5" pattern="\d{5}" inputmode="numeric"
                                placeholder="12345" required>
                            <small class="text-muted d-block mt-1">Gunakan angka yang mudah diingat.</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-regis shadow-sm" id="btnDaftar">
                                <i class="bi bi-check-all me-1"></i> SELESAI & DAFTAR
                            </button>
                            <a href="login.php" class="btn btn-light text-muted rounded-pill">Batal</a>
                        </div>

                    </form>
                </div>
            </div>
            <div class="text-center mt-4 text-muted small">
                &copy; 2026 BPR Ekadharma - Integrity &amp; Service
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const inputNama  = document.getElementById('inputNama');
    const statusNama = document.getElementById('statusNama');
    const iconNama   = document.getElementById('iconNamaInner');
    const btnDaftar  = document.getElementById('btnDaftar');

    let debounceTimer = null;
    let namaTerdaftar = false; // flag global

    // ── Fungsi cek nama ke server ────────────────────────────
    async function cekNama(nama) {
        if (!nama.trim()) {
            resetStatus();
            return;
        }

        // Tampilkan loading
        iconNama.className   = 'bi bi-hourglass-split text-muted';
        statusNama.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Mengecek ketersediaan nama...</span>';
        inputNama.classList.remove('is-valid', 'is-invalid', 'is-warning');

        try {
            const resp = await fetch(`?cek_nama=${encodeURIComponent(nama)}`);
            const data = await resp.json();

            if (data.ada) {
                // ── Nama persis sudah terdaftar ─────────────
                namaTerdaftar = true;
                inputNama.classList.add('is-invalid');
                iconNama.className = 'bi bi-x-circle-fill text-danger';
                statusNama.innerHTML =
                    `<span class="text-danger fw-semibold">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        Nama <strong>${escHtml(nama)}</strong> sudah terdaftar. Gunakan nama sesuai SK atau hubungi admin.
                    </span>`;
                btnDaftar.disabled = true;

            } else if (data.mirip && data.mirip.length > 0) {
                // ── Ada nama mirip (partial match) ──────────
                namaTerdaftar = false; // boleh lanjut daftar
                inputNama.classList.add('is-warning');
                inputNama.style.borderColor = '#ffc107';
                iconNama.className = 'bi bi-exclamation-triangle-fill text-warning';

                const namaList = data.mirip.map(n => `<strong>${escHtml(n)}</strong>`).join(', ');
                const keyword  = escHtml(nama);

                statusNama.innerHTML =
                    `<span class="text-warning fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Nama <strong>${keyword}</strong> cocok dengan data: ${namaList}.
                        Apakah Anda adalah <strong>${keyword}</strong> yang lain?
                        Jika iya, lanjutkan pendaftaran dengan nama lengkap sesuai SK.
                    </span>`;
                btnDaftar.disabled = false;

            } else {
                // ── Nama tersedia sepenuhnya ─────────────────
                namaTerdaftar = false;
                inputNama.classList.add('is-valid');
                inputNama.style.borderColor = '';
                iconNama.className = 'bi bi-check-circle-fill text-success';
                statusNama.innerHTML =
                    `<span class="text-success">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        Nama tersedia.
                    </span>`;
                btnDaftar.disabled = false;
            }
        } catch (err) {
            // Jika gagal fetch (misal offline) — biarkan submit, server tetap cek
            resetStatus();
        }
    }

    function resetStatus() {
        namaTerdaftar = false;
        inputNama.classList.remove('is-valid', 'is-invalid', 'is-warning');
        inputNama.style.borderColor = '';
        iconNama.className = 'bi bi-person text-muted';
        statusNama.innerHTML = '';
        btnDaftar.disabled = false;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Event: saat user mengetik nama ───────────────────────
    inputNama.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        btnDaftar.disabled = true;
        const val = this.value.trim();
        if (!val) { resetStatus(); return; }
        debounceTimer = setTimeout(() => cekNama(val), 500);
    });

    // ── Event: saat user meninggalkan field nama ─────────────
    inputNama.addEventListener('blur', function () {
        clearTimeout(debounceTimer);
        const val = this.value.trim();
        if (val) cekNama(val);
    });

    // ── Cegah submit jika nama sudah terdaftar (exact) ───────
    document.getElementById('formRegis').addEventListener('submit', function (e) {
        if (namaTerdaftar) {
            e.preventDefault();
            inputNama.focus();
            statusNama.innerHTML =
                `<span class="text-danger fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Nama sudah terdaftar. Pendaftaran tidak dapat dilanjutkan.
                </span>`;
            return false;
        }
        btnDaftar.disabled = true;
        btnDaftar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mendaftarkan...';
    });

    // ── Jalankan cek langsung jika nama sudah terisi (dari POST error) ──
    const namaAwal = inputNama.value.trim();
    if (namaAwal) cekNama(namaAwal);
})();
</script>
</body>
</html>