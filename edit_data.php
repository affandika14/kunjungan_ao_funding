<?php
session_start();

// 1. Cek Keamanan Session
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// Ambil parameter dari URL
$id         = $_GET['id'] ?? '';
$jenis      = $_GET['jenis'] ?? '';
$id_pegawai = $_SESSION['pegawai_id']; 

// Menangkap parameter filter tambahan agar tidak hilang saat redirect kembali
$bulan_filter = $_GET['bulan'] ?? '';
$tahun_filter = $_GET['tahun'] ?? '';

if (!$id || !$jenis) { 
    die("Data tidak valid."); 
}

// Mapping Tabel
$tables = [
    'rencana'    => 'trx_rencana_kegiatan',
    'prospek'    => 'trx_prospek_ao',
    'kunjungan'  => 'trx_kunjungan_ao',
    'funding'    => 'trx_prospek_funding',
    'collection' => 'trx_collection_funding',
    'analisa'    => 'trx_analisa_kredit'
];

if (!array_key_exists($jenis, $tables)) {
    die("Jenis laporan tidak dikenal.");
}

$table = $tables[$jenis];

// 2. Ambil Data Lama
$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ? AND pegawai_id = ?");
$stmt->execute([$id, $id_pegawai]);
$d = $stmt->fetch();

if (!$d) { 
    die("Data tidak ditemukan atau Anda tidak memiliki akses."); 
}

$status_update = "";
$pesan_error = "";

// 3. Proses Update Data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validasi kolektibilitas (hanya jenis kunjungan): harus angka yang ada di reff_kolektibilitas
        $kol_val = null;
        if ($jenis == 'kunjungan') {
            $kol_raw = $_POST['kolektibilitas'] ?? '';
            if (!is_string($kol_raw) || !ctype_digit(trim($kol_raw))) {
                throw new Exception("Kolektibilitas tidak valid.");
            }
            $kol_val = (int) trim($kol_raw);
            $cekKol = $conn->prepare("SELECT 1 FROM reff_kolektibilitas WHERE kode = ?");
            $cekKol->execute([$kol_val]);
            if ($cekKol->fetchColumn() === false) {
                throw new Exception("Kolektibilitas tidak valid.");
            }
        }

                $nama_file = $d['bukti_kegiatan'] ?? '-'; 

        if (!empty($_FILES['foto_baru']['name'])) {
            // Validasi tipe (MIME) dan ukuran (5MB), sebelum file lama dihapus
            $mime_ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $ftype = mime_content_type($_FILES['foto_baru']['tmp_name']);
            if ($ftype === false || !isset($mime_ext[$ftype])) {
                throw new Exception("Tipe file foto tidak didukung.");
            }
            if ($_FILES['foto_baru']['size'] > 5242880) {
                throw new Exception("Ukuran foto terlalu besar. Maksimal 5MB.");
            }

            $target_dir = "uploads/";
            if ($nama_file != '-' && file_exists($target_dir . $nama_file)) {
                unlink($target_dir . $nama_file);
            }
            $ext = $mime_ext[$ftype];
            $nama_file = "EDIT_" . time() . "_" . rand(100, 999) . "." . $ext;
            move_uploaded_file($_FILES['foto_baru']['tmp_name'], $target_dir . $nama_file);
        }

        if ($jenis == 'rencana') {
            $sql = "UPDATE trx_rencana_kegiatan SET tanggal=?, rencana_kegiatan=?, nama_nasabah=?, alamat_desa=?, kecamatan=?, kabupaten_kota=?, no_hp=?, hasil_yang_diharapkan=?, catatan=? WHERE id=? AND pegawai_id=?";
            $conn->prepare($sql)->execute([$_POST['tanggal'], $_POST['rencana_kegiatan'], $_POST['nama_nasabah'], $_POST['alamat_desa'], $_POST['kecamatan'], $_POST['kabupaten_kota'], $_POST['no_hp'], $_POST['hasil_yang_diharapkan'], $_POST['catatan'], $id, $id_pegawai]);
        
        } elseif ($jenis == 'prospek') {
            $sql = "UPDATE trx_prospek_ao SET tanggal_kunjungan=?, nama_calon_debitur=?, alamat_desa=?, kecamatan=?, kabupaten_kota=?, usaha_pekerjaan=?, produk_ditawarkan=?, no_hp=?, respon=?, keterangan=?, status=?, bukti_kegiatan=? WHERE id=? AND pegawai_id=?";
            $conn->prepare($sql)->execute([$_POST['tanggal'], $_POST['nama_calon'], $_POST['alamat'], $_POST['kec'], $_POST['kab'], $_POST['usaha'], $_POST['produk'], $_POST['no_hp'], $_POST['respon'], $_POST['keterangan'], $_POST['status'], $nama_file, $id, $id_pegawai]);
        
        } elseif ($jenis == 'kunjungan') {
            $sql = "UPDATE trx_kunjungan_ao SET tanggal_kunjungan=?, nama_nasabah=?, alamat_desa=?, kecamatan=?, kabupaten_kota=?, usaha_pekerjaan=?, keperluan_kunjungan=?, no_hp=?, respon=?, keterangan=?, kolektibilitas=?, bukti_kegiatan=? WHERE id=? AND pegawai_id=?";
            $conn->prepare($sql)->execute([$_POST['tanggal'], $_POST['nama_nasabah'], $_POST['alamat'], $_POST['kec'], $_POST['kab'], $_POST['usaha'], $_POST['keperluan'], $_POST['no_hp'], $_POST['respon'], $_POST['keterangan'], $kol_val, $nama_file, $id, $id_pegawai]);

        } elseif ($jenis == 'funding') {
            $sql = "UPDATE trx_prospek_funding SET tanggal_kunjungan=?, nama_prospek=?, jenis_prospek=?, no_hp=?, alamat_desa=?, kecamatan=?, kabupaten_kota=?, pekerjaan_usaha=?, estimasi_dana=?, produk_ditawarkan=?, ketertarikan_nasabah=?, hasil_kunjungan=?, status_prospek=?, catatan_marketing=?, bukti_kegiatan=? WHERE id=? AND pegawai_id=?";
            $conn->prepare($sql)->execute([$_POST['tanggal'], $_POST['nama_prospek'], $_POST['jenis_prospek'], $_POST['no_hp'], $_POST['alamat'], $_POST['kec'], $_POST['kab'], $_POST['usaha'], $_POST['estimasi'], $_POST['produk'], $_POST['ketertarikan'], $_POST['hasil'], $_POST['status'], $_POST['catatan'], $nama_file, $id, $id_pegawai]);

        } elseif ($jenis == 'collection') {
            // Logika tanda strip untuk saldo jika kosong
            $s_seb = ($_POST['saldo_seb'] !== "") ? $_POST['saldo_seb'] : "-";
            $setoran = ($_POST['setoran'] !== "") ? $_POST['setoran'] : "-";
            $s_ses = ($_POST['saldo_ses'] !== "") ? $_POST['saldo_ses'] : "-";

            $sql = "UPDATE trx_collection_funding SET tanggal=?, wilayah_resort=?, desa_lokasi=?, kecamatan=?, kabupaten_kota=?, nama_nasabah=?, produk_nasabah=?, status_nasabah=?, saldo_sebelum=?, setoran_hari_ini=?, saldo_setelah=?, metode_setoran=?, frekuensi_setoran=?, potensi_peningkatan=?, produk_lain_ditawarkan=?, kendala_lapangan=?, catatan=?, bukti_kegiatan=? WHERE id=? AND pegawai_id=?";
            $conn->prepare($sql)->execute([$_POST['tanggal'], $_POST['wilayah'], $_POST['desa'], $_POST['kec'], $_POST['kab'], $_POST['nama'], $_POST['produk'], $_POST['status_n'], $s_seb, $setoran, $s_ses, $_POST['metode'], $_POST['frekuensi'], $_POST['potensi'], $_POST['produk_l'], $_POST['kendala'], $_POST['catatan'], $nama_file, $id, $id_pegawai]);

        } elseif ($jenis == 'analisa') {
            $sql = "UPDATE trx_analisa_kredit SET nama_calon_debitur=?, plafond_dimohon=?, jangka_waktu=?, usaha=?, jenis_permohonan=?, suku_bunga=?, tanggal_pengerjaan=?, keperluan_kunjungan=?, keterangan=? WHERE id=? AND pegawai_id=?";
            $conn->prepare($sql)->execute([$_POST['nama_debitur'], $_POST['plafond'], $_POST['jangka_waktu'], $_POST['usaha'], $_POST['jenis_permohonan'], $_POST['bunga'], $_POST['tanggal'], $_POST['keperluan'], $_POST['keterangan'], $id, $id_pegawai]);
        }

        // Simpan status sukses ke session
        $_SESSION['status_update'] = "sukses";
        
        // Redirect kembali dengan membawa filter yang sama
        header("Location: monitoring_petugas.php?jenis=$jenis&bulan=$bulan_filter&tahun=$tahun_filter");
        exit;
    } catch(Exception $e) {
        $status_update = "error";
        $pesan_error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data <?= strtoupper($jenis) ?> - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .card-edit { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(25, 135, 84, 0.08); }
        .header-green { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); color: white; border-radius: 15px 15px 0 0; }
        .form-label { font-weight: 700; color: #146c43; font-size: 0.85rem; text-transform: uppercase; }
        .btn-update { background-color: #198754; border: none; color: white; font-weight: bold; transition: 0.3s; padding: 12px 30px; border-radius: 10px; }
        .btn-update:hover { background-color: #0d5a36; color: white; }
        .preview-img { max-width: 200px; border-radius: 10px; border: 2px solid #ddd; }
        .section-title { color: #198754; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-bottom: 15px; font-weight: 600; font-size: 0.95rem; }
    </style>
</head>
<body class="p-3 p-md-4">
<div class="container" style="max-width: 950px;">
    <div class="card card-edit">
        <div class="header-green p-4 text-center">
            <h4 class="mb-0 fw-bold text-uppercase"><i class="bi bi-pencil-square me-2"></i>EDIT DATA <?= $jenis ?></h4>
        </div>
        <div class="card-body p-4 bg-white">
            <form method="POST" enctype="multipart/form-data" id="formEdit">
                <div class="row g-3">

                <?php if ($jenis == 'rencana'): ?>
                    <div class="col-md-6"><label class="form-label">Tanggal Rencana</label><input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Nama Nasabah / Prospek</label><input type="text" name="nama_nasabah" class="form-control" value="<?= $d['nama_nasabah'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Alamat / Desa</label><input type="text" name="alamat_desa" class="form-control" value="<?= $d['alamat_desa'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">No HP / WhatsApp</label><input type="number" name="no_hp" class="form-control" value="<?= $d['no_hp'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Kecamatan</label><input type="text" name="kecamatan" class="form-control" value="<?= $d['kecamatan'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Kabupaten / Kota</label><input type="text" name="kabupaten_kota" class="form-control" value="<?= $d['kabupaten_kota'] ?>" required></div>
                    <div class="col-12"><label class="form-label">Rencana Kegiatan</label><textarea name="rencana_kegiatan" class="form-control" rows="3" required><?= $d['rencana_kegiatan'] ?></textarea></div>
                    <div class="col-12"><label class="form-label">Hasil yang Diharapkan</label><textarea name="hasil_yang_diharapkan" class="form-control" rows="3" required><?= $d['hasil_yang_diharapkan'] ?></textarea></div>
                    <div class="col-12"><label class="form-label">Catatan Tambahan</label><textarea name="catatan" class="form-control" rows="2"><?= $d['catatan'] ?></textarea></div>

                <?php elseif ($jenis == 'prospek'): ?>
                    <div class="col-md-4"><label class="form-label">Tanggal Kunjungan</label><input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal_kunjungan'] ?>" required></div>
                    <div class="col-md-8"><label class="form-label">Nama Calon Debitur</label><input type="text" name="nama_calon" class="form-control" value="<?= $d['nama_calon_debitur'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Alamat / Desa</label><input type="text" name="alamat" class="form-control" value="<?= $d['alamat_desa'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Kecamatan</label><input type="text" name="kec" class="form-control" value="<?= $d['kecamatan'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Kabupaten</label><input type="text" name="kab" class="form-control" value="<?= $d['kabupaten_kota'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Usaha / Pekerjaan</label><input type="text" name="usaha" class="form-control" value="<?= $d['usaha_pekerjaan'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">No HP</label><input type="number" name="no_hp" class="form-control" value="<?= $d['no_hp'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Produk Ditawarkan</label>
                        <select name="produk" class="form-select">
                            <option value="Kredit Umum" <?= $d['produk_ditawarkan'] == 'Kredit Umum' ? 'selected' : '' ?>>Kredit Umum</option>
                            <option value="Kredit Mikro" <?= $d['produk_ditawarkan'] == 'Kredit Mikro' ? 'selected' : '' ?>>Kredit Mikro</option>
                            <option value="Kredit Kilau Emas" <?= $d['produk_ditawarkan'] == 'Kredit Kilau Emas' ? 'selected' : '' ?>>Kredit Kilau Emas</option>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Respon Calon Debitur</label><textarea name="respon" class="form-control" rows="3" required><?= $d['respon'] ?></textarea></div>
                    <div class="col-md-6"><label class="form-label">Status Prospek</label>
                        <select name="status" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_status ORDER BY id ASC");
                            while($st = $q->fetch()) echo "<option value='{$st['nama_status']}' ".($d['status']==$st['nama_status']?'selected':'').">{$st['nama_status']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Keterangan Tambahan</label><textarea name="keterangan" class="form-control" rows="1"><?= $d['keterangan'] ?></textarea></div>

                <?php elseif ($jenis == 'kunjungan'): ?>
                    <div class="col-md-4"><label class="form-label">Tanggal Kunjungan</label><input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal_kunjungan'] ?>" required></div>
                    <div class="col-md-8"><label class="form-label">Nama Nasabah</label><input type="text" name="nama_nasabah" class="form-control" value="<?= $d['nama_nasabah'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Alamat / Desa</label><input type="text" name="alamat" class="form-control" value="<?= $d['alamat_desa'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Kecamatan</label><input type="text" name="kec" class="form-control" value="<?= $d['kecamatan'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Kabupaten</label><input type="text" name="kab" class="form-control" value="<?= $d['kabupaten_kota'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Usaha / Pekerjaan</label><input type="text" name="usaha" class="form-control" value="<?= $d['usaha_pekerjaan'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">No HP Nasabah</label><input type="number" name="no_hp" class="form-control" value="<?= $d['no_hp'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Kolektibilitas Saat Ini</label>
                        <select name="kolektibilitas" class="form-select">
                            <?php
                            $kol_now  = trim((string) ($d['kolektibilitas'] ?? ''));
                            $kol_now  = ctype_digit($kol_now) ? (string)(int)$kol_now : '';
                            $kol_list = $conn->query("SELECT kode, nama FROM reff_kolektibilitas ORDER BY kode ASC")->fetchAll();
                            $kol_ada  = false;
                            foreach ($kol_list as $kl) { if ((string)$kl['kode'] === $kol_now) { $kol_ada = true; break; } }
                            if (!$kol_ada) echo "<option value='' selected disabled>-</option>";
                            foreach ($kol_list as $kl) {
                                $sel = ((string)$kl['kode'] === $kol_now) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($kl['kode'])."' $sel>".htmlspecialchars($kl['nama'])."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Keperluan Kunjungan</label>
                        <select name="keperluan" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_keperluan_ao ORDER BY id ASC");
                            while($kp = $q->fetch()) echo "<option value='{$kp['nama_keperluan']}' ".($d['keperluan_kunjungan']==$kp['nama_keperluan']?'selected':'').">{$kp['nama_keperluan']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Respon Nasabah / Hasil</label><textarea name="respon" class="form-control" rows="3" required><?= $d['respon'] ?></textarea></div>
                    <div class="col-12"><label class="form-label">Keterangan Tambahan</label><textarea name="keterangan" class="form-control" rows="2"><?= $d['keterangan'] ?></textarea></div>

                <?php elseif ($jenis == 'funding'): ?>
                    <div class="col-md-4"><label class="form-label">Tanggal Kunjungan</label><input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal_kunjungan'] ?>" required></div>
                    <div class="col-md-8"><label class="form-label">Nama Prospek</label><input type="text" name="nama_prospek" class="form-control" value="<?= $d['nama_prospek'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Jenis Prospek</label>
                        <select name="jenis_prospek" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_jenis_prospek ORDER BY id ASC");
                            while($j = $q->fetch()) echo "<option value='{$j['nama_jenis']}' ".($d['jenis_prospek']==$j['nama_jenis']?'selected':'').">{$j['nama_jenis']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">No HP / WhatsApp</label><input type="number" name="no_hp" class="form-control" value="<?= $d['no_hp'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Usaha / Pekerjaan</label><input type="text" name="usaha" class="form-control" value="<?= $d['pekerjaan_usaha'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Alamat / Desa</label><input type="text" name="alamat" class="form-control" value="<?= $d['alamat_desa'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Kecamatan</label><input type="text" name="kec" class="form-control" value="<?= $d['kecamatan'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Kabupaten</label><input type="text" name="kab" class="form-control" value="<?= $d['kabupaten_kota'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Estimasi Dana (Rp)</label><input type="number" name="estimasi" class="form-control" value="<?= $d['estimasi_dana'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Produk Ditawarkan</label>
                        <select name="produk" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_produk_funding ORDER BY nama_produk ASC");
                            while($p = $q->fetch()) echo "<option value='{$p['nama_produk']}' ".($d['produk_ditawarkan']==$p['nama_produk']?'selected':'').">{$p['nama_produk']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Ketertarikan Nasabah</label>
                        <select name="ketertarikan" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_ketertarikan ORDER BY id ASC");
                            while($k = $q->fetch()) echo "<option value='{$k['level_ketertarikan']}' ".($d['ketertarikan_nasabah']==$k['level_ketertarikan']?'selected':'').">{$k['level_ketertarikan']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Status Prospek (Funnel)</label>
                        <select name="status" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_status ORDER BY id ASC");
                            while($st = $q->fetch()) echo "<option value='{$st['nama_status']}' ".($d['status_prospek']==$st['nama_status']?'selected':'').">{$st['nama_status']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Hasil Kunjungan</label>
                        <select name="hasil" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_hasil_funding ORDER BY id ASC");
                            while($h = $q->fetch()) echo "<option value='{$h['hasil']}' ".($d['hasil_kunjungan']==$h['hasil']?'selected':'').">{$h['hasil']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Catatan Marketing</label><textarea name="catatan" class="form-control" rows="2"><?= $d['catatan_marketing'] ?></textarea></div>

                <?php elseif ($jenis == 'collection'): ?>
                    <div class="col-md-6"><label class="form-label">Tanggal Transaksi</label><input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Nama Nasabah</label><input type="text" name="nama" class="form-control" value="<?= $d['nama_nasabah'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Saldo Sebelum (Rp) - Opsional</label><input type="number" name="saldo_seb" class="form-control" value="<?= is_numeric($d['saldo_sebelum']) ? $d['saldo_sebelum'] : '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Setoran (Rp) - Opsional</label><input type="number" name="setoran" class="form-control" value="<?= is_numeric($d['setoran_hari_ini']) ? $d['setoran_hari_ini'] : '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Saldo Setelah (Rp)</label><input type="number" name="saldo_ses" class="form-control" value="<?= is_numeric($d['saldo_setelah']) ? $d['saldo_setelah'] : '' ?>" readonly></div>
                    <div class="col-md-3"><label class="form-label">Wilayah</label><input type="text" name="wilayah" class="form-control" value="<?= $d['wilayah_resort'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Desa</label><input type="text" name="desa" class="form-control" value="<?= $d['desa_lokasi'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Kecamatan</label><input type="text" name="kec" class="form-control" value="<?= $d['kecamatan'] ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Kabupaten</label><input type="text" name="kab" class="form-control" value="<?= $d['kabupaten_kota'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Produk Funding</label>
                        <select name="produk" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_produk_funding ORDER BY nama_produk ASC");
                            while($rp = $q->fetch()) echo "<option value='{$rp['nama_produk']}' ".($d['produk_nasabah']==$rp['nama_produk']?'selected':'').">{$rp['nama_produk']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Status Nasabah</label>
                        <select name="status_n" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_status_nasabah ORDER BY nama ASC");
                            while($rn = $q->fetch()) echo "<option value='{$rn['nama']}' ".($d['status_nasabah']==$rn['nama']?'selected':'').">{$rn['nama']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Metode Setoran</label>
                        <select name="metode" class="form-select">
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_metode_setoran");
                            while($rm = $q->fetch()) echo "<option value='{$rm['nama']}' ".($d['metode_setoran']==$rm['nama']?'selected':'').">{$rm['nama']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Frekuensi Setoran</label>
                        <select name="frekuensi" class="form-select">
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_frekuensi_setoran");
                            while($rf = $q->fetch()) echo "<option value='{$rf['nama']}' ".($d['frekuensi_setoran']==$rf['nama']?'selected':'').">{$rf['nama']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Potensi Peningkatan</label>
                        <select name="potensi" class="form-select">
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_potensi");
                            while($rt = $q->fetch()) echo "<option value='{$rt['nama']}' ".($d['potensi_peningkatan']==$rt['nama']?'selected':'').">{$rt['nama']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Cross-Selling (Produk Lain)</label>
                        <select name="produk_l" class="form-select">
                            <option value="-">Tidak Ada</option>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_produk_funding ORDER BY nama_produk ASC");
                            while($rp = $q->fetch()) echo "<option value='{$rp['nama_produk']}' ".($d['produk_lain_ditawarkan']==$rp['nama_produk']?'selected':'').">{$rp['nama_produk']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Kendala Lapangan</label><textarea name="kendala" class="form-control" rows="1"><?= $d['kendala_lapangan'] ?></textarea></div>
                    <div class="col-12"><label class="form-label">Catatan</label><textarea name="catatan" class="form-control" rows="1"><?= $d['catatan'] ?></textarea></div>

                <?php elseif ($jenis == 'analisa'): ?>
                    <div class="col-md-8"><label class="form-label">Nama Calon Debitur</label><input type="text" name="nama_debitur" class="form-control" value="<?= $d['nama_calon_debitur'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Tanggal Pengerjaan</label><input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal_pengerjaan'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Plafond Dimohon (Rp)</label><input type="number" name="plafond" class="form-control" value="<?= $d['plafond_dimohon'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Jangka Waktu</label><input type="text" name="jangka_waktu" class="form-control" value="<?= $d['jangka_waktu'] ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Suku Bunga</label><input type="text" name="bunga" class="form-control" value="<?= $d['suku_bunga'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Usaha / Pekerjaan</label><input type="text" name="usaha" class="form-control" value="<?= $d['usaha'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Jenis Permohonan</label>
                        <select name="jenis_permohonan" class="form-select" required>
                            <?php 
                            $q = $conn->query("SELECT * FROM reff_jenis_permohonan ORDER BY nama_permohonan ASC");
                            while($rp = $q->fetch()) echo "<option value='{$rp['nama_permohonan']}' ".($d['jenis_permohonan']==$rp['nama_permohonan']?'selected':'').">{$rp['nama_permohonan']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Keperluan Kunjungan / Lokasi Analisa</label><textarea name="keperluan" class="form-control" rows="2"><?= $d['keperluan_kunjungan'] ?></textarea></div>
                    <div class="col-12"><label class="form-label">Keterangan / Kesimpulan Analisa</label><textarea name="keterangan" class="form-control" rows="4"><?= $d['keterangan'] ?></textarea></div>
                <?php endif; ?>

                <?php if (in_array($jenis, ['prospek', 'kunjungan', 'funding', 'collection'])): ?>
                    <div class="col-12 mt-4 pt-3 border-top">
                        <label class="form-label d-block"><i class="bi bi-camera me-1"></i> BUKTI FOTO KEGIATAN</label>
                        <div class="d-flex align-items-start gap-3">
                            <div>
                                <small class="text-muted d-block mb-2">Foto Saat Ini:</small>
                                <?php if(!empty($d['bukti_kegiatan']) && $d['bukti_kegiatan'] != '-'): ?>
                                    <img src="uploads/<?= $d['bukti_kegiatan'] ?>" class="preview-img mb-2 shadow-sm">
                                <?php else: ?>
                                    <div class="p-3 bg-light rounded text-muted small border">Belum ada foto</div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <small class="text-success d-block mb-2 fw-bold">Ganti Foto Baru (Opsional):</small>
                                <input type="file" name="foto_baru" id="foto_baru" class="form-control" accept="image/*">
                                <small class="text-muted">Pilih file baru jika ingin mengubah foto bukti (Maks 5MB).</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                </div>

                <div class="mt-5 text-center">
                    <button type="submit" class="btn btn-update px-5 py-2 shadow-sm rounded-pill" id="btnUpdate">
                        <i class="bi bi-save2 me-2"></i>SIMPAN PERUBAHAN
                    </button>
                    <a href="monitoring_petugas.php?jenis=<?= $jenis ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>" class="btn btn-outline-secondary px-5 py-2 rounded-pill ms-2">
                        BATAL
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logic hitung saldo otomatis
        <?php if($jenis == 'collection'): ?>
        const sAwal = document.querySelector('input[name="saldo_seb"]');
        const sSetor = document.querySelector('input[name="setoran"]');
        const sAkhir = document.querySelector('input[name="saldo_ses"]');
        const calc = () => {
            let awal = parseFloat(sAwal.value) || 0;
            let setor = parseFloat(sSetor.value) || 0;
            sAkhir.value = (sAwal.value === "" && sSetor.value === "") ? "" : awal + setor;
        };
        sAwal.addEventListener('input', calc);
        sSetor.addEventListener('input', calc);
        <?php endif; ?>

        // Validasi Ukuran File (JS)
        const inputFoto = document.getElementById('foto_baru');
        if(inputFoto) {
            inputFoto.onchange = function() {
                if(this.files[0].size > 5242880) { // 5MB
                    Swal.fire({ icon: 'warning', title: 'File Terlalu Besar', text: 'Ukuran foto maksimal adalah 5MB.', confirmButtonColor: '#ffc107' });
                    this.value = "";
                }
            };
        }

        // Pop-up Gagal
        <?php if($status_update === "error"): ?>
            Swal.fire({ icon: 'error', title: 'Gagal Memperbarui!', text: '<?= addslashes($pesan_error) ?>', confirmButtonColor: '#d33' });
        <?php endif; ?>

        // Anti double submit
        document.getElementById('formEdit').onsubmit = function() {
            const btn = document.getElementById('btnUpdate');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
        };
    });
</script>
</body>
</html>