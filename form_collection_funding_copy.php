<?php
session_start();

// 1. Proteksi Session
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// 2. Ambil ID dari Session (Bukan dari $_GET agar aman)
$id_pegawai = $_SESSION['pegawai_id'];
$nama_display = $_SESSION['nama_user'] ?? "Guest";

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // --- LOGIKA MAPPING KEPALA KAS OTOMATIS ---
        $kantor_kas = $_POST['kantor_kas'];
        $kas = strtolower($kantor_kas);
        $nama_kepala = "";

        if (strpos($kas, "sumoroto") !== false) $nama_kepala = "WANDA MIFTAKHUL JANNAH";
        elseif (strpos($kas, "ngariboyo") !== false || strpos($kas, "parang") !== false) $nama_kepala = "VITRI DWI CAHYANI";
        elseif (strpos($kas, "dolopo") !== false || strpos($kas, "jiwan") !== false) $nama_kepala = "Asrul Anggriawan Azis";
        elseif (strpos($kas, "rejoso") !== false) $nama_kepala = "agus sunaryo";
        elseif (strpos($kas, "takeran") !== false || strpos($kas, "lembeyan") !== false) $nama_kepala = "PIGGY MEI AYYUDHI";
        elseif (strpos($kas, "bendo") !== false || strpos($kas, "maospati") !== false) $nama_kepala = "SUPRAPTO";
        elseif (strpos($kas, "karas") !== false || strpos($kas, "karangrejo") !== false || strpos($kas, "barat") !== false) $nama_kepala = "ANDIKA DEVI RISMIANTO";
        elseif (strpos($kas, "panekan") !== false || strpos($kas, "magetan") !== false) $nama_kepala = "ZEQI IRAWAN";

        // Cari ID Kepala Kas di tabel pegawai
        $id_kepala_kas = null;
        if ($nama_kepala != "") {
            $stmt_id = $conn->prepare("SELECT id FROM pegawai WHERE nama LIKE ? LIMIT 1");
            $stmt_id->execute(["%$nama_kepala%"]);
            $kepala = $stmt_id->fetch();
            if ($kepala) $id_kepala_kas = $kepala['id'];
        }

        // Proses Upload Foto
        $nama_file = "";
        if (!empty($_FILES['bukti_kegiatan']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $nama_file = "COLL_" . time() . "_" . $_FILES['bukti_kegiatan']['name'];
            move_uploaded_file($_FILES['bukti_kegiatan']['tmp_name'], $target_dir . $nama_file);
        }

        // Simpan Data dengan id_kepala_kas_lokasi
        $sql = "INSERT INTO trx_collection_funding 
                (pegawai_id, id_kepala_kas_lokasi, tanggal, wilayah_resort, desa_lokasi, kecamatan, kabupaten_kota, nama_nasabah, produk_nasabah, status_nasabah, saldo_sebelum, setoran_hari_ini, saldo_setelah, metode_setoran, frekuensi_setoran, potensi_peningkatan, produk_lain_ditawarkan, kendala_lapangan, catatan, bukti_kegiatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $conn->prepare($sql)->execute([
            $id_pegawai, $id_kepala_kas, $_POST['tanggal'], $_POST['wilayah'], $_POST['desa'], $_POST['kec'], $_POST['kab'], 
            $_POST['nasabah'], $_POST['produk'], $_POST['status'], $_POST['saldo_awal'], $_POST['setoran'], 
            $_POST['saldo_akhir'], $_POST['metode'], $_POST['frekuensi'], $_POST['potensi'], 
            $_POST['produk_lain'], $_POST['kendala'], $_POST['catatan'], $nama_file
        ]);

        $pesan = "<div class='alert alert-success shadow-sm border-0 border-start border-4 border-success animate__animated animate__fadeIn'>Data Koleksi Berhasil Disimpan & Termapping ke Kepala Kas!</div>";
    } catch(PDOException $e) {
        $pesan = "<div class='alert alert-danger shadow-sm border-0 border-start border-4 border-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Collection Funding - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        
        /* Navbar Gradasi Hijau */
        .navbar-custom {
            background: linear-gradient(135deg, #198754 0%, #0d5a36 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .card-form {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 25px rgba(25, 135, 84, 0.08);
        }

        .card-header-green {
            background-color: #ffffff;
            border-bottom: 2px solid #198754;
            color: #198754;
            border-radius: 15px 15px 0 0 !important;
        }

        .form-label {
            font-weight: 700;
            color: #146c43;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15);
        }

        .input-kas {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
        }

        .btn-save {
            background-color: #198754;
            border: none;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 10px;
            transition: 0.3s;
        }

        .btn-save:hover {
            background-color: #0d5a36;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
            color: white;
        }

        .saldo-highlight {
            background-color: #e9f5ee;
            border-left: 4px solid #198754;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand small fw-bold" href="dashboard_petugas.php?id=<?= $id_pegawai ?>">
            <i class="bi bi-arrow-left-circle me-1"></i> KEMBALI
        </a>
        <span class="navbar-text text-white">
            <i class="bi bi-person-fill me-1"></i> PETUGAS: <strong><?= htmlspecialchars($nama_display) ?></strong>
        </span>
    </div>
</nav>

<div class="container mb-5">
    <div class="card card-form shadow-sm border-0">
        <div class="card-header card-header-green py-3 text-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2"></i> FORM COLLECTION FUNDING</h5>
        </div>
        <div class="card-body p-4">
            <?= $pesan ?>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tanggal Transaksi</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="col-md-9 mb-3">
                        <label class="form-label text-danger">Kantor Kas Wilayah Transaksi</label>
                        <select name="kantor_kas" class="form-select input-kas fw-bold" required>
                            <option value="">-- Pilih Kantor Kas --</option>
                            <option value="Sumoroto">Kantor Kas Sumoroto</option>
                            <option value="Ngariboyo">Kantor Kas Ngariboyo</option>
                            <option value="Parang">Kantor Kas Parang</option>
                            <option value="Dolopo">Kantor Kas Dolopo</option>
                            <option value="Jiwan">Kantor Kas Jiwan</option>
                            <option value="Rejoso">Kantor Kas Rejoso</option>
                            <option value="Takeran">Kantor Kas Takeran</option>
                            <option value="Lembeyan">Kantor Kas Lembeyan</option>
                            <option value="Bendo">Kantor Kas Bendo</option>
                            <option value="Maospati">Kantor Kas Maospati</option>
                            <option value="Karas">Kantor Kas Karas</option>
                            <option value="Karangrejo">Kantor Kas Karangrejo</option>
                            <option value="Barat">Kantor Kas Barat</option>
                            <option value="Panekan">Kantor Kas Panekan</option>
                            <option value="Magetan">Kantor Kas Magetan</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Wilayah / Resort</label>
                        <input type="text" name="wilayah" class="form-control" placeholder="Contoh: Resort 01" required>
                    </div>
                    <div class="col-md-9 mb-3">
                        <label class="form-label">Nama Lengkap Nasabah</label>
                        <input type="text" name="nasabah" class="form-control" placeholder="Masukkan nama nasabah..." required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Desa / Lokasi</label>
                        <input type="text" name="desa" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kecamatan</label>
                        <input type="text" name="kec" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kabupaten</label>
                        <input type="text" name="kab" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Produk Funding</label>
                        <select name="produk" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_produk_funding ORDER BY nama_produk ASC");
                            while($r = $q->fetch()) echo "<option value='{$r['nama_produk']}'>{$r['nama_produk']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status Nasabah</label>
                        <select name="status" class="form-select" required>
                            <option value="">-- Pilih Status --</option>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_status_nasabah ORDER BY nama ASC");
                            while($r = $q->fetch()) echo "<option value='{$r['nama']}'>{$r['nama']}</option>";
                            ?>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <div class="p-3 rounded-3 saldo-highlight">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-muted">Saldo Sebelum (Rp)</label>
                                    <input type="number" id="saldo_awal" name="saldo_awal" class="form-control border-success" placeholder="0" oninput="hitungSaldo()" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-success">Setoran Hari Ini (Rp)</label>
                                    <input type="number" id="setoran" name="setoran" class="form-control border-success fw-bold" placeholder="0" oninput="hitungSaldo()" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-primary">Saldo Setelah Setoran (Rp)</label>
                                    <input type="number" id="saldo_akhir" name="saldo_akhir" class="form-control bg-white border-primary fw-bold" readonly required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Metode Setoran</label>
                        <select name="metode" class="form-select">
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_metode_setoran");
                            while($r = $q->fetch()) echo "<option value='{$r['nama']}'>{$r['nama']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Frekuensi Setoran</label>
                        <select name="frekuensi" class="form-select">
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_frekuensi_setoran");
                            while($r = $q->fetch()) echo "<option value='{$r['nama']}'>{$r['nama']}</option>";
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Potensi Peningkatan</label>
                        <select name="potensi" class="form-select">
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_potensi");
                            while($r = $q->fetch()) echo "<option value='{$r['nama']}'>{$r['nama']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cross-Selling (Produk Lain)</label>
                        <select name="produk_lain" class="form-select">
                            <option value="-">Tidak Ada</option>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_produk_funding ORDER BY nama_produk ASC");
                            while($r = $q->fetch()) echo "<option value='{$r['nama_produk']}'>{$r['nama_produk']}</option>";
                            ?>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Kendala di Lapangan</label>
                        <textarea name="kendala" class="form-control" rows="2" placeholder="Tulis kendala jika ada..."></textarea>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Catatan Tambahan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan untuk kantor..."></textarea>
                    </div>

                    <div class="col-12 mb-4">
                        <label class="form-label"><i class="bi bi-camera-fill me-1"></i> Bukti Foto Kegiatan</label>
                        <input type="file" name="bukti_kegiatan" class="form-control" accept="image/*">
                        <small class="text-muted">Ambil foto saat melakukan penarikan/setoran nasabah.</small>
                    </div>

                    <div class="col-12 border-top pt-4 text-center">
                        <button type="reset" class="btn btn-outline-secondary px-4 me-2 rounded-pill">RESET</button>
                        <button type="submit" class="btn btn-save px-5 shadow-sm rounded-pill">
                            <i class="bi bi-check-circle-fill me-2"></i> SIMPAN SETORAN
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function hitungSaldo() {
    let awal = parseFloat(document.getElementById('saldo_awal').value) || 0;
    let setoran = parseFloat(document.getElementById('setoran').value) || 0;
    document.getElementById('saldo_akhir').value = awal + setoran;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>