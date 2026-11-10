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

$id_pegawai = $_SESSION['pegawai_id'];

// Tentukan link "kembali" sesuai role (atasan kembali ke dashboard atasan)
$link_kembali = ($_SESSION['role'] ?? '') === 'atasan' ? 'dashboard_atasan.php' : 'dashboard_form_kegiatan.php';

// Ambil Data Petugas
$stmt = $conn->prepare("SELECT nama, kantor FROM pegawai WHERE id = ?");
$stmt->execute([$id_pegawai]);
$p = $stmt->fetch();

if (!$p) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ============================================================
//  Filter Parameter
// ============================================================
$tgl_mulai    = !empty($_GET['tgl_mulai'])   ? $_GET['tgl_mulai']   : date('Y-m-d');
$tgl_selesai  = !empty($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// Whitelist jenis agar tidak bisa diinjeksi kolom sembarangan
$allowed_jenis = ['rencana','prospek','kunjungan','funding','collection','analisa'];
$filter_jenis  = in_array($_GET['jenis'] ?? '', $allowed_jenis) ? $_GET['jenis'] : 'rencana';

$params = [
    'id'    => $id_pegawai,
    'tgl_m' => $tgl_mulai,
    'tgl_s' => $tgl_selesai,
];

// Query & tgl_key berdasarkan jenis
switch ($filter_jenis) {
    case 'rencana':
        $tgl_key = 'tanggal';
        $sql = "SELECT * FROM trx_rencana_kegiatan WHERE pegawai_id = :id AND tanggal BETWEEN :tgl_m AND :tgl_s";
        break;
    case 'prospek':
        $tgl_key = 'tanggal_kunjungan';
        $sql = "SELECT * FROM trx_prospek_ao WHERE pegawai_id = :id AND tanggal_kunjungan BETWEEN :tgl_m AND :tgl_s";
        break;
    case 'kunjungan':
        $tgl_key = 'tanggal_kunjungan';
        $sql = "SELECT * FROM trx_kunjungan_ao WHERE pegawai_id = :id AND tanggal_kunjungan BETWEEN :tgl_m AND :tgl_s";
        break;
    case 'funding':
        $tgl_key = 'tanggal_kunjungan';
        $sql = "SELECT * FROM trx_prospek_funding WHERE pegawai_id = :id AND tanggal_kunjungan BETWEEN :tgl_m AND :tgl_s";
        break;
    case 'collection':
        $tgl_key = 'tanggal';
        $sql = "SELECT * FROM trx_collection_funding WHERE pegawai_id = :id AND tanggal BETWEEN :tgl_m AND :tgl_s";
        break;
    case 'analisa':
        $tgl_key = 'tanggal_pengerjaan';
        $sql = "SELECT * FROM trx_analisa_kredit WHERE pegawai_id = :id AND tanggal_pengerjaan BETWEEN :tgl_m AND :tgl_s";
        break;
    default:
        $tgl_key = 'id';
        $sql = "SELECT 1 WHERE 0"; // tidak akan mengembalikan apapun
        break;
}

$sql .= " ORDER BY {$tgl_key} DESC";

try {
    $stmt_data = $conn->prepare($sql);
    $stmt_data->execute($params);
    $results = $stmt_data->fetchAll();
} catch (PDOException $e) {
    error_log("[MONITORING PETUGAS ERROR] " . $e->getMessage());
    $results = [];
}

// ============================================================
//  AJAX ENDPOINT: ambil data satu kategori untuk export multi
//  Dipanggil dari JS dengan ?export_jenis=xxx&tgl_mulai=&tgl_selesai=
// ============================================================
if (isset($_GET['export_jenis'])) {
    header('Content-Type: application/json');
    $allowed_export = ['rencana','prospek','kunjungan','funding','collection','analisa'];
    $ej = in_array($_GET['export_jenis'], $allowed_export) ? $_GET['export_jenis'] : '';
    if (!$ej) { echo json_encode([]); exit; }

    $etgl_m = $_GET['tgl_mulai']   ?? date('Y-m-d');
    $etgl_s = $_GET['tgl_selesai'] ?? date('Y-m-d');
    $ep = ['id' => $id_pegawai, 'tgl_m' => $etgl_m, 'tgl_s' => $etgl_s];

    $equery_map = [
        'rencana'    => ["SELECT tanggal,rencana_kegiatan,nama_nasabah,alamat_desa,kecamatan,kabupaten_kota,no_hp,hasil_yang_diharapkan,catatan FROM trx_rencana_kegiatan WHERE pegawai_id=:id AND tanggal BETWEEN :tgl_m AND :tgl_s ORDER BY tanggal DESC"],
        'prospek'    => ["SELECT tanggal_kunjungan,nama_calon_debitur,alamat_desa,kecamatan,kabupaten_kota,usaha_pekerjaan,produk_ditawarkan,no_hp,status,keterangan FROM trx_prospek_ao WHERE pegawai_id=:id AND tanggal_kunjungan BETWEEN :tgl_m AND :tgl_s ORDER BY tanggal_kunjungan DESC"],
        'kunjungan'  => ["SELECT tanggal_kunjungan,nama_nasabah,alamat_desa,kecamatan,kabupaten_kota,usaha_pekerjaan,keperluan_kunjungan,no_hp,kolektibilitas,keterangan FROM trx_kunjungan_ao WHERE pegawai_id=:id AND tanggal_kunjungan BETWEEN :tgl_m AND :tgl_s ORDER BY tanggal_kunjungan DESC"],
        'funding'    => ["SELECT tanggal_kunjungan,nama_prospek,jenis_prospek,no_hp,alamat_desa,kecamatan,kabupaten_kota,pekerjaan_usaha,estimasi_dana,status_prospek FROM trx_prospek_funding WHERE pegawai_id=:id AND tanggal_kunjungan BETWEEN :tgl_m AND :tgl_s ORDER BY tanggal_kunjungan DESC"],
        'collection' => ["SELECT tanggal,nama_nasabah,desa_lokasi,kecamatan,produk_nasabah,status_nasabah,saldo_sebelum,setoran_hari_ini,saldo_setelah,metode_setoran FROM trx_collection_funding WHERE pegawai_id=:id AND tanggal BETWEEN :tgl_m AND :tgl_s ORDER BY tanggal DESC"],
        'analisa'    => ["SELECT tanggal_pengerjaan,nama_calon_debitur,plafond_dimohon,jangka_waktu,usaha,jenis_permohonan,suku_bunga,keperluan_kunjungan,keterangan FROM trx_analisa_kredit WHERE pegawai_id=:id AND tanggal_pengerjaan BETWEEN :tgl_m AND :tgl_s ORDER BY tanggal_pengerjaan DESC"],
    ];

    try {
        $es = $conn->prepare($equery_map[$ej][0]);
        $es->execute($ep);
        $edata = $es->fetchAll(PDO::FETCH_NUM);
        echo json_encode($edata);
    } catch (Exception $ex) {
        echo json_encode([]);
    }
    exit;
}


// Helper: format angka dengan aman (handle nilai "-" atau null)
function safeNumber($val): string {
    if ($val === null || $val === '' || $val === '-') return '-';
    return is_numeric($val) ? number_format((float)$val, 0, ',', '.') : htmlspecialchars($val);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring - <?= htmlspecialchars($p['nama'] ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .print-only { display: none; }
        @media print {
            @page { size: landscape; margin: 0.5cm; }
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { font-family: Arial, sans-serif; font-size: 8px; background: white; }
            .table-print { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            .table-print th, .table-print td { border: 1px solid black !important; padding: 3px; }
            .table-print th { background-color: #f0f0f0 !important; text-align: center; }
            .paraf-box { margin-top: 20px; width: 100%; }
        }
        .card-filter { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(25,135,84,0.08); }
        .table-web { font-size: 12px; }
        .thead-green { background-color: #146c43; color: white; text-align: center; }
        .btn-green { background-color: #198754; color: white; border: none; font-weight: 600; }
        .btn-green:hover { background-color: #0d5a36; color: white; }
        .badge-cat { background-color: rgba(25,135,84,0.1); color: #198754; border: 1px solid rgba(25,135,84,0.2); }
    </style>
</head>
<body class="bg-light">
<!-- Data untuk export JS -->
<span id="export-nama"    style="display:none"><?= htmlspecialchars($p['nama']   ?? '') ?></span>
<span id="export-cabang"  style="display:none"><?= htmlspecialchars($p['kantor'] ?? '') ?></span>
<span id="export-periode" style="display:none"><?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?></span>

<nav class="navbar navbar-dark navbar-custom mb-3 no-print shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand small fw-bold" href="<?= $link_kembali ?>"><i class="bi bi-arrow-left-circle me-1"></i> KEMBALI</a>
        <div class="d-flex gap-2 align-items-center">
            <button onclick="bukaModalExport('excel')" class="btn btn-sm fw-bold rounded-pill px-3" style="background:#1d6f42;color:white;">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
            </button>
            <button onclick="bukaModalExport('pdf')" class="btn btn-sm fw-bold rounded-pill px-3" style="background:#c0392b;color:white;">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
            </button>
            <button onclick="window.print()" class="btn btn-light btn-sm fw-bold rounded-pill px-3">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <!-- Filter -->
    <div class="card card-filter shadow-sm mb-4 no-print border-0">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="small fw-bold text-success text-uppercase">Periode Tanggal</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai) ?>">
                        <span class="input-group-text bg-light text-muted">s/d</span>
                        <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($tgl_selesai) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-success text-uppercase">Jenis Laporan</label>
                    <select name="jenis" class="form-select form-select-sm">
                        <option value="rencana"    <?= $filter_jenis=='rencana'    ? 'selected':'' ?>>Rencana Kegiatan</option>
                        <option value="prospek"    <?= $filter_jenis=='prospek'    ? 'selected':'' ?>>Prospek AO</option>
                        <option value="kunjungan"  <?= $filter_jenis=='kunjungan'  ? 'selected':'' ?>>Kunjungan AO</option>
                        <option value="funding"    <?= $filter_jenis=='funding'    ? 'selected':'' ?>>Prospek Funding</option>
                        <option value="collection" <?= $filter_jenis=='collection' ? 'selected':'' ?>>Collection Funding</option>
                        <option value="analisa"    <?= $filter_jenis=='analisa'    ? 'selected':'' ?>>Analisa Kredit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-green btn-sm w-100 fw-bold shadow-sm rounded-pill">
                        <i class="bi bi-search me-1"></i> TAMPILKAN
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <span class="small text-muted">Total data: <strong><?= count($results) ?></strong></span>
                </div>
            </form>
        </div>
    </div>

    <!-- Versi Web -->
    <div class="no-print mt-2 pb-5">
        <h5 class="mb-3 text-success fw-bold text-uppercase">
            <i class="bi bi-file-earmark-text me-2"></i>Daftar Aktivitas: <?= htmlspecialchars(str_replace('_',' ',$filter_jenis)) ?>
        </h5>

        <?php if (empty($results)): ?>
        <div class="alert alert-warning text-center shadow-sm">
            <i class="bi bi-inbox me-2"></i> Tidak ada data untuk periode <strong><?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?></strong> pada kategori <strong><?= htmlspecialchars($filter_jenis) ?></strong>.
        </div>
        <?php else: ?>
        <div class="table-responsive shadow-sm rounded-3">
            <table id="tabel-data" class="table table-hover table-bordered mb-0 table-web align-middle bg-white">
                <thead>
                    <tr class="thead-green">
                        <th width="40">No</th>
                        <?php if ($filter_jenis=='rencana'): ?>
                            <th>Tanggal</th><th>Kegiatan</th><th>Nasabah</th><th>Desa</th><th>Kecamatan</th><th>Kabupaten</th><th>No HP</th><th>Harapan</th><th>Catatan</th>
                        <?php elseif ($filter_jenis=='prospek'): ?>
                            <th>Tanggal</th><th>Calon Debitur</th><th>Desa</th><th>Kecamatan</th><th>Kabupaten</th><th>Usaha</th><th>Produk</th><th>No HP</th><th>Status</th><th>Ket</th>
                        <?php elseif ($filter_jenis=='kunjungan'): ?>
                            <th>Tanggal</th><th>Nama Nasabah</th><th>Desa</th><th>Kecamatan</th><th>Kabupaten</th><th>Usaha</th><th>Keperluan</th><th>No HP</th><th>Kol</th><th>Ket</th>
                        <?php elseif ($filter_jenis=='funding'): ?>
                            <th>Tanggal</th><th>Nama Prospek</th><th>Jenis</th><th>No HP</th><th>Desa</th><th>Kecamatan</th><th>Kabupaten</th><th>Usaha</th><th>Estimasi</th><th>Status</th>
                        <?php elseif ($filter_jenis=='collection'): ?>
                            <th>Tanggal</th><th>Nama Nasabah</th><th>Desa</th><th>Kecamatan</th><th>Produk</th><th>Status</th><th>Saldo Seb</th><th>Setoran</th><th>Saldo Ses</th><th>Metode</th>
                        <?php elseif ($filter_jenis=='analisa'): ?>
                            <th>Tanggal</th><th>Calon Debitur</th><th>Plafond</th><th>Tenor</th><th>Usaha</th><th>Permohonan</th><th>Bunga</th><th>Keperluan</th><th>Keterangan</th>
                        <?php endif; ?>
                        <th width="50">Foto</th>
                        <th class="no-print" width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($results as $r): ?>
                    <tr>
                        <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                        <?php if ($filter_jenis=='rencana'): ?>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($r['rencana_kegiatan']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nama_nasabah']) ?></td>
                            <td><?= htmlspecialchars($r['alamat_desa']) ?></td>
                            <td><?= htmlspecialchars($r['kecamatan']) ?></td>
                            <td><?= htmlspecialchars($r['kabupaten_kota']) ?></td>
                            <td><?= htmlspecialchars($r['no_hp']) ?></td>
                            <td><?= htmlspecialchars($r['hasil_yang_diharapkan']) ?></td>
                            <td><?= htmlspecialchars($r['catatan']) ?></td>
                        <?php elseif ($filter_jenis=='prospek'): ?>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal_kunjungan'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nama_calon_debitur']) ?></td>
                            <td><?= htmlspecialchars($r['alamat_desa']) ?></td>
                            <td><?= htmlspecialchars($r['kecamatan']) ?></td>
                            <td><?= htmlspecialchars($r['kabupaten_kota']) ?></td>
                            <td><?= htmlspecialchars($r['usaha_pekerjaan']) ?></td>
                            <td><?= htmlspecialchars($r['produk_ditawarkan']) ?></td>
                            <td><?= htmlspecialchars($r['no_hp']) ?></td>
                            <td><span class="badge badge-cat"><?= htmlspecialchars($r['status']) ?></span></td>
                            <td><?= htmlspecialchars($r['keterangan']) ?></td>
                        <?php elseif ($filter_jenis=='kunjungan'): ?>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal_kunjungan'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nama_nasabah']) ?></td>
                            <td><?= htmlspecialchars($r['alamat_desa']) ?></td>
                            <td><?= htmlspecialchars($r['kecamatan']) ?></td>
                            <td><?= htmlspecialchars($r['kabupaten_kota']) ?></td>
                            <td><?= htmlspecialchars($r['usaha_pekerjaan']) ?></td>
                            <td><?= htmlspecialchars($r['keperluan_kunjungan']) ?></td>
                            <td><?= htmlspecialchars($r['no_hp']) ?></td>
                            <td><span class="badge bg-dark"><?= htmlspecialchars($r['kolektibilitas']) ?></span></td>
                            <td><?= htmlspecialchars($r['keterangan']) ?></td>
                        <?php elseif ($filter_jenis=='funding'): ?>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal_kunjungan'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nama_prospek']) ?></td>
                            <td><?= htmlspecialchars($r['jenis_prospek']) ?></td>
                            <td><?= htmlspecialchars($r['no_hp']) ?></td>
                            <td><?= htmlspecialchars($r['alamat_desa']) ?></td>
                            <td><?= htmlspecialchars($r['kecamatan']) ?></td>
                            <td><?= htmlspecialchars($r['kabupaten_kota']) ?></td>
                            <td><?= htmlspecialchars($r['pekerjaan_usaha']) ?></td>
                            <td class="fw-bold text-success"><?= safeNumber($r['estimasi_dana']) ?></td>
                            <td><?= htmlspecialchars($r['status_prospek']) ?></td>
                        <?php elseif ($filter_jenis=='collection'): ?>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nama_nasabah']) ?></td>
                            <td><?= htmlspecialchars($r['desa_lokasi']) ?></td>
                            <td><?= htmlspecialchars($r['kecamatan']) ?></td>
                            <td><?= htmlspecialchars($r['produk_nasabah']) ?></td>
                            <td><?= htmlspecialchars($r['status_nasabah']) ?></td>
                            <td><?= safeNumber($r['saldo_sebelum']) ?></td>
                            <td class="fw-bold text-success"><?= safeNumber($r['setoran_hari_ini']) ?></td>
                            <td><?= safeNumber($r['saldo_setelah']) ?></td>
                            <td><?= htmlspecialchars($r['metode_setoran']) ?></td>
                        <?php elseif ($filter_jenis=='analisa'): ?>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['tanggal_pengerjaan'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nama_calon_debitur']) ?></td>
                            <td class="fw-bold text-primary"><?= safeNumber($r['plafond_dimohon']) ?></td>
                            <td><?= htmlspecialchars($r['jangka_waktu']) ?></td>
                            <td><?= htmlspecialchars($r['usaha']) ?></td>
                            <td><?= htmlspecialchars($r['jenis_permohonan']) ?></td>
                            <td><?= htmlspecialchars($r['suku_bunga']) ?></td>
                            <td><?= htmlspecialchars($r['keperluan_kunjungan']) ?></td>
                            <td><?= htmlspecialchars($r['keterangan']) ?></td>
                        <?php endif; ?>

                        <td class="text-center">
                            <?php if (!empty($r['bukti_kegiatan']) && $r['bukti_kegiatan'] !== '-'): ?>
                                <button type="button" class="btn btn-sm btn-info text-white p-1" onclick="viewPhoto('uploads/<?= htmlspecialchars($r['bukti_kegiatan']) ?>')">
                                    <i class="bi bi-camera"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center no-print">
                            <div class="btn-group shadow-sm">
                                <a href="edit_data.php?id=<?= $r['id'] ?>&jenis=<?= $filter_jenis ?>" class="btn btn-light btn-sm text-warning border" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                <a href="hapus_data.php?id=<?= $r['id'] ?>&jenis=<?= $filter_jenis ?>" class="btn btn-light btn-sm text-danger border" onclick="return confirm('Hapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Versi Cetak -->
    <div class="print-only">
        <div class="text-center mb-3">
            <h4 class="mb-0">LAPORAN <?= strtoupper(str_replace(['_','trx_'],' ',$filter_jenis)) ?></h4>
            <p class="mb-0 text-uppercase">CABANG: <?= htmlspecialchars($p['kantor'] ?? '') ?> | PETUGAS: <?= htmlspecialchars($p['nama'] ?? '') ?></p>
            <p class="small">Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?></p>
        </div>
        <table class="table-print">
            <thead>
                <tr>
                    <th>NO</th>
                    <?php if ($filter_jenis=='rencana'): ?>
                        <th>TANGGAL</th><th>RENCANA KEGIATAN</th><th>NAMA NASABAH</th><th>ALAMAT/DESA</th><th>KECAMATAN</th><th>KABUPATEN/KOTA</th><th>NO HP</th><th>HASIL DIHARAPKAN</th><th>CATATAN</th>
                    <?php elseif ($filter_jenis=='prospek'): ?>
                        <th>TANGGAL</th><th>NAMA CALON DEBITUR</th><th>ALAMAT/DESA</th><th>KECAMATAN</th><th>KABUPATEN/KOTA</th><th>USAHA/PEKERJAAN</th><th>PRODUK DITAWARKAN</th><th>NO HP</th><th>RESPON</th><th>KETERANGAN</th><th>STATUS</th>
                    <?php elseif ($filter_jenis=='kunjungan'): ?>
                        <th>TANGGAL</th><th>NAMA NASABAH</th><th>ALAMAT/DESA</th><th>KECAMATAN</th><th>KABUPATEN/KOTA</th><th>USAHA/PEKERJAAN</th><th>KEPERLUAN KUNJUNGAN</th><th>NO HP</th><th>RESPON</th><th>KETERANGAN</th><th>KOLEKTIBILITAS</th>
                    <?php elseif ($filter_jenis=='funding'): ?>
                        <th>TANGGAL</th><th>NAMA PROSPEK</th><th>JENIS PROSPEK</th><th>NO HP</th><th>ALAMAT/DESA</th><th>KECAMATAN</th><th>KABUPATEN/KOTA</th><th>PEKERJAAN/USAHA</th><th>ESTIMASI DANA</th><th>PRODUK DITAWARKAN</th><th>KETERTARIKAN</th><th>HASIL KUNJUNGAN</th><th>STATUS PROSPEK</th>
                    <?php elseif ($filter_jenis=='collection'): ?>
                        <th>TANGGAL</th><th>WILAYAH/RESORT</th><th>DESA/LOKASI</th><th>KECAMATAN</th><th>KABUPATEN/KOTA</th><th>NAMA NASABAH</th><th>PRODUK NASABAH</th><th>STATUS NASABAH</th><th>SALDO SEBELUM</th><th>SETORAN HARI INI</th><th>SALDO SETELAH</th><th>METODE</th><th>KENDALA</th>
                    <?php elseif ($filter_jenis=='analisa'): ?>
                        <th>TANGGAL</th><th>NAMA CALON DEBITUR</th><th>PLAFOND DIMOHON</th><th>JANGKA WAKTU</th><th>USAHA</th><th>JENIS PERMOHONAN</th><th>SUKU BUNGA</th><th>KEPERLUAN KUNJUNGAN</th><th>KETERANGAN</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($results as $r): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <?php if ($filter_jenis=='rencana'): ?>
                        <td><?= $r['tanggal'] ?></td><td><?= htmlspecialchars($r['rencana_kegiatan']) ?></td><td><?= htmlspecialchars($r['nama_nasabah']) ?></td><td><?= htmlspecialchars($r['alamat_desa']) ?></td><td><?= htmlspecialchars($r['kecamatan']) ?></td><td><?= htmlspecialchars($r['kabupaten_kota']) ?></td><td><?= htmlspecialchars($r['no_hp']) ?></td><td><?= htmlspecialchars($r['hasil_yang_diharapkan']) ?></td><td><?= htmlspecialchars($r['catatan']) ?></td>
                    <?php elseif ($filter_jenis=='prospek'): ?>
                        <td><?= $r['tanggal_kunjungan'] ?></td><td><?= htmlspecialchars($r['nama_calon_debitur']) ?></td><td><?= htmlspecialchars($r['alamat_desa']) ?></td><td><?= htmlspecialchars($r['kecamatan']) ?></td><td><?= htmlspecialchars($r['kabupaten_kota']) ?></td><td><?= htmlspecialchars($r['usaha_pekerjaan']) ?></td><td><?= htmlspecialchars($r['produk_ditawarkan']) ?></td><td><?= htmlspecialchars($r['no_hp']) ?></td><td><?= htmlspecialchars($r['respon']) ?></td><td><?= htmlspecialchars($r['keterangan']) ?></td><td><?= htmlspecialchars($r['status']) ?></td>
                    <?php elseif ($filter_jenis=='kunjungan'): ?>
                        <td><?= $r['tanggal_kunjungan'] ?></td><td><?= htmlspecialchars($r['nama_nasabah']) ?></td><td><?= htmlspecialchars($r['alamat_desa']) ?></td><td><?= htmlspecialchars($r['kecamatan']) ?></td><td><?= htmlspecialchars($r['kabupaten_kota']) ?></td><td><?= htmlspecialchars($r['usaha_pekerjaan']) ?></td><td><?= htmlspecialchars($r['keperluan_kunjungan']) ?></td><td><?= htmlspecialchars($r['no_hp']) ?></td><td><?= htmlspecialchars($r['respon']) ?></td><td><?= htmlspecialchars($r['keterangan']) ?></td><td><?= htmlspecialchars($r['kolektibilitas']) ?></td>
                    <?php elseif ($filter_jenis=='funding'): ?>
                        <td><?= $r['tanggal_kunjungan'] ?></td><td><?= htmlspecialchars($r['nama_prospek']) ?></td><td><?= htmlspecialchars($r['jenis_prospek']) ?></td><td><?= htmlspecialchars($r['no_hp']) ?></td><td><?= htmlspecialchars($r['alamat_desa']) ?></td><td><?= htmlspecialchars($r['kecamatan']) ?></td><td><?= htmlspecialchars($r['kabupaten_kota']) ?></td><td><?= htmlspecialchars($r['pekerjaan_usaha']) ?></td><td><?= safeNumber($r['estimasi_dana']) ?></td><td><?= htmlspecialchars($r['produk_ditawarkan']) ?></td><td><?= htmlspecialchars($r['ketertarikan_nasabah']) ?></td><td><?= htmlspecialchars($r['hasil_kunjungan']) ?></td><td><?= htmlspecialchars($r['status_prospek']) ?></td>
                    <?php elseif ($filter_jenis=='collection'): ?>
                        <td><?= $r['tanggal'] ?></td><td><?= htmlspecialchars($r['wilayah_resort']) ?></td><td><?= htmlspecialchars($r['desa_lokasi']) ?></td><td><?= htmlspecialchars($r['kecamatan']) ?></td><td><?= htmlspecialchars($r['kabupaten_kota']) ?></td><td><?= htmlspecialchars($r['nama_nasabah']) ?></td><td><?= htmlspecialchars($r['produk_nasabah']) ?></td><td><?= htmlspecialchars($r['status_nasabah']) ?></td><td><?= safeNumber($r['saldo_sebelum']) ?></td><td><?= safeNumber($r['setoran_hari_ini']) ?></td><td><?= safeNumber($r['saldo_setelah']) ?></td><td><?= htmlspecialchars($r['metode_setoran']) ?></td><td><?= htmlspecialchars($r['kendala_lapangan']) ?></td>
                    <?php elseif ($filter_jenis=='analisa'): ?>
                        <td><?= $r['tanggal_pengerjaan'] ?></td><td><?= htmlspecialchars($r['nama_calon_debitur']) ?></td><td><?= safeNumber($r['plafond_dimohon']) ?></td><td><?= htmlspecialchars($r['jangka_waktu']) ?></td><td><?= htmlspecialchars($r['usaha']) ?></td><td><?= htmlspecialchars($r['jenis_permohonan']) ?></td><td><?= htmlspecialchars($r['suku_bunga']) ?></td><td><?= htmlspecialchars($r['keperluan_kunjungan']) ?></td><td><?= htmlspecialchars($r['keterangan']) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="paraf-box">
            <table style="width:300px;float:right;border:1px solid black;text-align:center;margin-top:20px;">
                <tr><td colspan="2" style="border-bottom:1px solid black;font-weight:bold;background:#eee;">MENGETAHUI</td></tr>
                <tr style="height:60px;">
                    <td width="50%" style="border-right:1px solid black;vertical-align:bottom;padding-bottom:5px;"><small style="font-size:8px;display:block;">PUK</small>( ..................... )</td>
                    <td style="vertical-align:bottom;padding-bottom:5px;font-weight:bold;">( <?= htmlspecialchars($p['nama']) ?> )</td>
                </tr>
            </table>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL: Pilih Kategori Export (Multi)
============================================================ -->
<div class="modal fade" id="modalExport" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="fw-bold mb-0" id="modalExportTitle">
                        <i class="bi bi-file-earmark-excel-fill me-2 text-success"></i>Export Excel
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Pilih kategori yang ingin diekspor</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <!-- Ceklist Semua -->
                <div class="form-check p-3 rounded-3 mb-3" style="background:#e9f5ee;border:2px solid #198754;">
                    <input class="form-check-input" type="checkbox" id="chkAll" onchange="toggleAllKategori(this)">
                    <label class="form-check-label fw-bold text-success" for="chkAll">
                        <i class="bi bi-check2-all me-1"></i> Cetak Semua Kategori
                    </label>
                </div>
                <!-- Daftar Kategori -->
                <div class="d-flex flex-column gap-2">
                    <div class="form-check p-2 px-3 rounded-2 border bg-white kategori-item">
                        <input class="form-check-input chk-kategori" type="checkbox" value="rencana" id="chkRencana" checked>
                        <label class="form-check-label" for="chkRencana"><i class="bi bi-calendar-check text-primary me-2"></i>Rencana Kegiatan</label>
                    </div>
                    <div class="form-check p-2 px-3 rounded-2 border bg-white kategori-item">
                        <input class="form-check-input chk-kategori" type="checkbox" value="prospek" id="chkProspek">
                        <label class="form-check-label" for="chkProspek"><i class="bi bi-person-plus text-info me-2"></i>Prospek AO</label>
                    </div>
                    <div class="form-check p-2 px-3 rounded-2 border bg-white kategori-item">
                        <input class="form-check-input chk-kategori" type="checkbox" value="kunjungan" id="chkKunjungan">
                        <label class="form-check-label" for="chkKunjungan"><i class="bi bi-geo-alt text-warning me-2"></i>Kunjungan AO</label>
                    </div>
                    <div class="form-check p-2 px-3 rounded-2 border bg-white kategori-item">
                        <input class="form-check-input chk-kategori" type="checkbox" value="funding" id="chkFunding">
                        <label class="form-check-label" for="chkFunding"><i class="bi bi-bank text-success me-2"></i>Prospek Funding</label>
                    </div>
                    <div class="form-check p-2 px-3 rounded-2 border bg-white kategori-item">
                        <input class="form-check-input chk-kategori" type="checkbox" value="collection" id="chkCollection">
                        <label class="form-check-label" for="chkCollection"><i class="bi bi-layers text-danger me-2"></i>Collection Funding</label>
                    </div>
                    <div class="form-check p-2 px-3 rounded-2 border bg-white kategori-item">
                        <input class="form-check-input chk-kategori" type="checkbox" value="analisa" id="chkAnalisa">
                        <label class="form-check-label" for="chkAnalisa"><i class="bi bi-calculator text-secondary me-2"></i>Analisa Kredit</label>
                    </div>
                </div>
                <!-- Loading bar -->
                <div id="exportProgress" class="mt-3" style="display:none">
                    <div class="progress" style="height:6px;">
                        <div id="exportProgressBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width:0%"></div>
                    </div>
                    <p id="exportProgressText" class="text-center small text-muted mt-1 mb-0"></p>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn rounded-pill px-4 fw-bold" id="btnDoExport"
                    style="background:#1d6f42;color:white;" onclick="doExportMulti()">
                    <i class="bi bi-download me-1"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Foto -->
<div class="modal fade" id="modalViewPhoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="imgPreview" src="" class="img-fluid rounded-3 shadow-sm" alt="Bukti Foto">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewPhoto(url) {
    document.getElementById('imgPreview').src = url;
    new bootstrap.Modal(document.getElementById('modalViewPhoto')).show();
}
</script>

<!-- ============================================================
     EXPORT: SheetJS (Excel) + jsPDF + AutoTable (PDF)
============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<script>
// ── Konfigurasi kolom per kategori ───────────────────────────
const EXPORT_CONFIG = {
    rencana    : { head:['No','Tanggal','Kegiatan','Nasabah','Desa','Kecamatan','Kabupaten','No HP','Harapan','Catatan'], cols:[0,1,2,3,4,5,6,7,8,9] },
    prospek    : { head:['No','Tanggal','Calon Debitur','Desa','Kecamatan','Kabupaten','Usaha','Produk','No HP','Status','Keterangan'], cols:[0,1,2,3,4,5,6,7,8,9,10] },
    kunjungan  : { head:['No','Tanggal','Nama Nasabah','Desa','Kecamatan','Kabupaten','Usaha','Keperluan','No HP','Kolektibilitas','Keterangan'], cols:[0,1,2,3,4,5,6,7,8,9,10] },
    funding    : { head:['No','Tanggal','Nama Prospek','Jenis','No HP','Desa','Kecamatan','Kabupaten','Usaha','Estimasi Dana','Status'], cols:[0,1,2,3,4,5,6,7,8,9,10] },
    collection : { head:['No','Tanggal','Nama Nasabah','Desa','Kecamatan','Produk','Status','Saldo Sebelum','Setoran','Saldo Setelah','Metode'], cols:[0,1,2,3,4,5,6,7,8,9,10] },
    analisa    : { head:['No','Tanggal','Calon Debitur','Plafond','Tenor','Usaha','Permohonan','Bunga','Keperluan','Keterangan'], cols:[0,1,2,3,4,5,6,7,8,9] },
};
const JUDUL = {
    rencana:'Rencana Kegiatan', prospek:'Prospek AO', kunjungan:'Kunjungan AO',
    funding:'Prospek Funding', collection:'Collection Funding', analisa:'Analisa Kredit'
};
// Urutan tampilan di export multi (sesuai permintaan: rencana dulu)
const URUTAN_KATEGORI = ['rencana','prospek','kunjungan','funding','collection','analisa'];

// ── Helper ───────────────────────────────────────────────────
function getJenis()  { return new URLSearchParams(window.location.search).get('jenis') || 'rencana'; }
function getTglParam() {
    const p = new URLSearchParams(window.location.search);
    return { tgl_mulai: p.get('tgl_mulai') || '', tgl_selesai: p.get('tgl_selesai') || '' };
}
function getLaporanInfo() {
    return {
        nama   : document.getElementById('export-nama').textContent.trim(),
        cabang : document.getElementById('export-cabang').textContent.trim(),
        periode: document.getElementById('export-periode').textContent.trim(),
    };
}
// Ambil data dari tabel di layar (untuk kategori aktif saat ini)
function getTableRows(cfg) {
    const rows = [];
    document.querySelectorAll('#tabel-data tbody tr').forEach(tr => {
        const tds = tr.querySelectorAll('td');
        if (tds.length < 2) return;
        rows.push(cfg.cols.map(i => tds[i] ? tds[i].innerText.trim() : ''));
    });
    return rows;
}

// ── Fetch data satu kategori via AJAX ────────────────────────
async function fetchKategori(jenis, tgl_mulai, tgl_selesai) {
    const url = `${window.location.pathname}?export_jenis=${jenis}&tgl_mulai=${tgl_mulai}&tgl_selesai=${tgl_selesai}`;
    const resp = await fetch(url);
    if (!resp.ok) throw new Error(`Gagal mengambil data ${jenis}`);
    const rawData = await resp.json(); // array of arrays (PDO::FETCH_NUM)
    // Tambahkan nomor urut di kolom pertama
    return rawData.map((row, i) => [String(i + 1), ...row]);
}

// ── Toggle semua checklist ───────────────────────────────────
function toggleAllKategori(chkAll) {
    document.querySelectorAll('.chk-kategori').forEach(c => { c.checked = chkAll.checked; });
    syncChkAll();
}
function syncChkAll() {
    const all  = document.querySelectorAll('.chk-kategori');
    const checked = document.querySelectorAll('.chk-kategori:checked');
    const chkAll = document.getElementById('chkAll');
    chkAll.indeterminate = checked.length > 0 && checked.length < all.length;
    chkAll.checked = checked.length === all.length;
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.chk-kategori').forEach(c => c.addEventListener('change', syncChkAll));
    // Set default checklist = kategori aktif saat ini
    const aktif = getJenis();
    document.querySelectorAll('.chk-kategori').forEach(c => { c.checked = (c.value === aktif); });
    syncChkAll();
});

// ── Buka modal export ────────────────────────────────────────
let currentExportType = 'excel';
function bukaModalExport(type) {
    currentExportType = type;
    const isExcel = type === 'excel';
    document.getElementById('modalExportTitle').innerHTML = isExcel
        ? '<i class="bi bi-file-earmark-excel-fill me-2 text-success"></i>Export Excel'
        : '<i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i>Export PDF';
    document.getElementById('btnDoExport').style.background = isExcel ? '#1d6f42' : '#c0392b';
    document.getElementById('exportProgress').style.display = 'none';
    document.getElementById('exportProgressBar').style.width = '0%';
    new bootstrap.Modal(document.getElementById('modalExport')).show();
}

// ── Tombol Download di dalam modal ───────────────────────────
async function doExportMulti() {
    const dipilih = [...document.querySelectorAll('.chk-kategori:checked')].map(c => c.value);
    if (!dipilih.length) { alert('Pilih minimal satu kategori.'); return; }

    // Urutkan sesuai URUTAN_KATEGORI
    const kategoriUrut = URUTAN_KATEGORI.filter(k => dipilih.includes(k));

    const btn      = document.getElementById('btnDoExport');
    const progress = document.getElementById('exportProgress');
    const bar      = document.getElementById('exportProgressBar');
    const txt      = document.getElementById('exportProgressText');
    btn.disabled   = true;
    btn.innerHTML  = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
    progress.style.display = 'block';

    const info      = getLaporanInfo();
    const { tgl_mulai, tgl_selesai } = getTglParam();
    const jenisAktif = getJenis();

    try {
        // Kumpulkan data semua kategori yang dipilih
        const allData = {};
        for (let i = 0; i < kategoriUrut.length; i++) {
            const k = kategoriUrut[i];
            bar.style.width = Math.round((i / kategoriUrut.length) * 60) + '%';
            txt.textContent = `Mengambil data: ${JUDUL[k]}...`;

            if (k === jenisAktif) {
                // Gunakan data yang sudah ada di tabel layar (lebih cepat)
                const cfg = EXPORT_CONFIG[k];
                allData[k] = getTableRows(cfg);
            } else {
                // Fetch dari server
                allData[k] = await fetchKategori(k, tgl_mulai, tgl_selesai);
            }
        }

        bar.style.width = '70%';
        txt.textContent = 'Membuat file...';

        if (currentExportType === 'excel') {
            await buildExcelMulti(kategoriUrut, allData, info, tgl_mulai, tgl_selesai);
        } else {
            await buildPDFMulti(kategoriUrut, allData, info, tgl_mulai, tgl_selesai);
        }

        bar.style.width = '100%';
        bar.classList.remove('progress-bar-animated');
        bar.classList.add('bg-success');
        txt.textContent = 'Selesai! File berhasil diunduh.';

        setTimeout(() => {
            bootstrap.Modal.getInstance(document.getElementById('modalExport')).hide();
            bar.classList.add('progress-bar-animated');
            bar.style.width = '0%';
        }, 1200);

    } catch (err) {
        txt.textContent = '❌ ' + err.message;
        bar.classList.replace('bg-success','bg-danger');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-download me-1"></i> Download';
    }
}

// ── Build Excel Multi-sheet ───────────────────────────────────
async function buildExcelMulti(kategoriUrut, allData, info, tgl_mulai, tgl_selesai) {
    const wb  = XLSX.utils.book_new();
    const tgl = new Date().toISOString().slice(0, 10);

    for (const k of kategoriUrut) {
        const cfg   = EXPORT_CONFIG[k];
        const rows  = allData[k] || [];
        const judul = JUDUL[k];

        const sheetData = [
            [`LAPORAN ${judul.toUpperCase()} - Bank Ekadharma`],
            [`Petugas: ${info.nama}  |  Cabang: ${info.cabang}`],
            [`Periode: ${info.periode}`],
            [],
            cfg.head,
            ...rows,
        ];

        const ws = XLSX.utils.aoa_to_sheet(sheetData);
        // Lebar kolom otomatis
        ws['!cols'] = cfg.head.map((h, i) => ({
            wch: Math.min(Math.max(h.length, ...rows.map(r => (r[i] || '').length)) + 2, 42)
        }));
        // Merge baris header info
        ws['!merges'] = [
            { s:{r:0,c:0}, e:{r:0,c:cfg.head.length-1} },
            { s:{r:1,c:0}, e:{r:1,c:cfg.head.length-1} },
            { s:{r:2,c:0}, e:{r:2,c:cfg.head.length-1} },
        ];

        // Nama sheet maks 31 karakter (batasan Excel)
        const sheetName = judul.slice(0, 31);
        XLSX.utils.book_append_sheet(wb, ws, sheetName);
    }

    const namaFile = kategoriUrut.length === 1
        ? `Laporan_${JUDUL[kategoriUrut[0]].replace(/ /g,'_')}_${tgl}.xlsx`
        : `Laporan_Semua_Kategori_${tgl}.xlsx`;
    XLSX.writeFile(wb, namaFile);
}

// ── Build PDF Multi-section (satu PDF, tiap kategori section baru) ──
async function buildPDFMulti(kategoriUrut, allData, info, tgl_mulai, tgl_selesai) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation:'landscape', unit:'mm', format:'a4' });
    const tgl = new Date().toISOString().slice(0, 10);

    let isFirstSection = true;

    for (const k of kategoriUrut) {
        const cfg   = EXPORT_CONFIG[k];
        const rows  = allData[k] || [];
        const judul = JUDUL[k];

        // Halaman baru untuk setiap kategori (kecuali yang pertama)
        if (!isFirstSection) doc.addPage();
        isFirstSection = false;

        // Header section
        doc.setFontSize(11); doc.setTextColor(20,108,67); doc.setFont('helvetica','bold');
        doc.text(`LAPORAN ${judul.toUpperCase()} - Bank Ekadharma`, 14, 12);
        doc.setFontSize(7.5); doc.setTextColor(60,60,60); doc.setFont('helvetica','normal');
        doc.text(`Petugas: ${info.nama}   |   Cabang: ${info.cabang}`, 14, 18);
        doc.text(`Periode: ${info.periode}`, 14, 22);
        doc.setDrawColor(20,108,67); doc.setLineWidth(0.3); doc.line(14, 24, 283, 24);

        if (!rows.length) {
            // Kategori kosong — tampilkan pesan
            doc.setFontSize(8); doc.setTextColor(150,150,150); doc.setFont('helvetica','italic');
            doc.text('Tidak ada data untuk periode ini.', 14, 32);
        } else {
            doc.autoTable({
                startY: 27,
                head  : [cfg.head],
                body  : rows,
                theme : 'grid',
                styles: { fontSize:6.5, cellPadding:1.8, overflow:'linebreak' },
                headStyles: { fillColor:[20,108,67], textColor:255, fontStyle:'bold', halign:'center' },
                alternateRowStyles: { fillColor:[240,247,244] },
                columnStyles: { 0:{ halign:'center', cellWidth:7 } },
                margin: { left:14, right:14 },
                // Lanjutkan di halaman yang sama jika tabel panjang
                pageBreak: 'auto',
                didDrawPage: (d) => {
                    const total = doc.internal.getNumberOfPages();
                    doc.setFontSize(6.5); doc.setTextColor(150);
                    doc.text(
                        `Hal ${d.pageNumber}/${total}  |  ${new Date().toLocaleString('id-ID')}`,
                        14, doc.internal.pageSize.height - 4
                    );
                }
            });
        }
    }

    const namaFile = kategoriUrut.length === 1
        ? `Laporan_${JUDUL[kategoriUrut[0]].replace(/ /g,'_')}_${tgl}.pdf`
        : `Laporan_Semua_Kategori_${tgl}.pdf`;
    doc.save(namaFile);
}

// ── Export single (alias untuk backward compat, tidak dipakai langsung) ──
function exportExcel() { bukaModalExport('excel'); }
function exportPDF()   { bukaModalExport('pdf');   }
</script>

</body>