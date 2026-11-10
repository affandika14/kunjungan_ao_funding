<?php
session_start();

// 1. Proteksi Session: Pastikan sudah login DAN perannya adalah atasan
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'atasan') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// 2. Ambil ID Atasan dari Session
$id_atasan = $_SESSION['pegawai_id'];

// Ambil info atasan yang sedang aktif
$stmt_atasan = $conn->prepare("SELECT nama, posisi, kantor FROM pegawai WHERE id = ?");
$stmt_atasan->execute([$id_atasan]);
$atasan = $stmt_atasan->fetch();

if (!$atasan) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- LOGIKA FILTER ---
$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$filter_petugas = $_GET['petugas_id'] ?? '';
$filter_tipe = $_GET['tipe'] ?? 'semua'; // 'semua' atau 'petugas'

// Ambil daftar bawahan untuk dropdown filter
$sql_b = "SELECT p.*, u.status 
          FROM mapping_atasan ma
          JOIN pegawai p ON ma.id_bawahan = p.id
          JOIN users u ON p.id = u.pegawai_id
          WHERE ma.id_atasan = ? AND u.status = 'aktif'
          ORDER BY p.nama ASC";

$stmt_b = $conn->prepare($sql_b);
$stmt_b->execute([$id_atasan]); // $id_atasan berasal dari session
$list_bawahan = $stmt_b->fetchAll();

// --- QUERY DATA (UNION) DENGAN GROUP BY PEGAWAI ---
/**
 * Kita melakukan UNION untuk mencari siapa saja yang mengisi laporan di rentang tanggal tersebut,
 * kemudian membungkusnya dalam subquery untuk melakukan GROUP BY agar satu nama hanya muncul sekali.
 */
$tables = [
    ['table' => 'trx_analisa_kredit', 'tgl' => 'tanggal_pengerjaan'],
    ['table' => 'trx_collection_funding', 'tgl' => 'tanggal'],
    ['table' => 'trx_prospek_funding', 'tgl' => 'tanggal_kunjungan'],
    ['table' => 'trx_prospek_ao', 'tgl' => 'tanggal_kunjungan'],
    ['table' => 'trx_kunjungan_ao', 'tgl' => 'tanggal_kunjungan'],
    ['table' => 'trx_rencana_kegiatan', 'tgl' => 'tanggal']
];

$union_parts = [];
foreach ($tables as $t) {
    $union_parts[] = "SELECT pegawai_id, {$t['tgl']} as tgl_data FROM {$t['table']}";
}

$union_sql = implode(" UNION ALL ", $union_parts);

$full_sql = "SELECT p.nama as nama_petugas, p.jabatan, u.pegawai_id, MAX(u.tgl_data) as tgl_terakhir 
             FROM ($union_sql) u
             JOIN pegawai p ON u.pegawai_id = p.id
             WHERE u.pegawai_id IN (SELECT id_bawahan FROM mapping_atasan WHERE id_atasan = :atasan)
             AND u.tgl_data BETWEEN :tgl1 AND :tgl2";

if (!empty($filter_petugas)) {
    $full_sql .= " AND u.pegawai_id = :petugas";
}

if ($filter_tipe === 'petugas') {
    $full_sql .= " AND p.jabatan = 'Staff'";
}

$full_sql .= " GROUP BY u.pegawai_id ORDER BY nama_petugas ASC";

$stmt_laporan = $conn->prepare($full_sql);
$stmt_laporan->bindValue(':atasan', $id_atasan);
$stmt_laporan->bindValue(':tgl1', $tgl_mulai);
$stmt_laporan->bindValue(':tgl2', $tgl_selesai);
if (!empty($filter_petugas)) {
    $stmt_laporan->bindValue(':petugas', $filter_petugas);
}
$stmt_laporan->execute();
$laporan = $stmt_laporan->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Atasan - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f7f4; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%) !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .filter-section { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 20px rgba(25, 135, 84, 0.08); }
        .form-label-custom { font-weight: 700; color: #146c43; font-size: 0.85rem; text-transform: uppercase; }
        .btn-green { background-color: #198754; border: none; color: white; font-weight: bold; transition: 0.3s; }
        .btn-green:hover { background-color: #0d5a36; color: white; }
        .table-card { border-radius: 15px; overflow: hidden; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .thead-green { background-color: #146c43; color: white; }
        .btn-detail { border: 1px solid #198754; color: #198754; font-weight: 600; border-radius: 20px; padding: 2px 15px; transition: 0.3s; text-decoration: none; }
        .btn-detail:hover { background-color: #198754; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom shadow mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">
            <i class="bi bi-shield-lock-fill me-2"></i>
            Monitoring Atasan: <strong><?= htmlspecialchars($atasan['nama']) ?></strong>
        </span>
        <div class="d-flex gap-2">
            <a href="dashboard_form_kegiatan.php" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-pencil-square"></i> Form Kegiatan
            </a>
            <a href="ganti_password.php" class="btn btn-sm btn-light text-success border-success rounded-pill px-3 fw-bold">
                <i class="bi bi-key-fill"></i> Ganti Pass
            </a>
            <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                <i class="bi bi-power"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="filter-section mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label-custom">Rentang Tanggal Aktivitas</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>">
                    <span class="input-group-text bg-light text-muted small">s/d</span>
                    <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>">
                </div>
            </div>

            <div class="col-md-5">
                <label class="form-label-custom">Pilih Nama Petugas</label>
                <select name="petugas_id" class="form-select form-select-sm">
                    <option value="">-- Semua Petugas Tim Anda --</option>
                    <?php foreach($list_bawahan as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $filter_petugas == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-green btn-sm w-100 shadow-sm">
                    <i class="bi bi-search me-1"></i> LIHAT AKTIVITAS
                </button>
            </div>
        </form>
    </div>

    <div class="card table-card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
            <div>
                <h6 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-person-fill me-2 text-success"></i>
                    LAPORAN SAYA
                </h6>
                <small class="text-muted">Riwayat laporan/aktivitas yang Anda input sendiri (terpisah dari laporan bawahan)</small>
            </div>
            <a href="monitoring_petugas.php" class="btn btn-detail btn-sm">
                <i class="bi bi-search me-1"></i> Lihat Laporan Saya
            </a>
        </div>
    </div>

    <div class="card table-card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-people-fill me-2 text-success"></i>
                DAFTAR PETUGAS AKTIF
            </h6>
            <small class="text-muted">Menampilkan petugas yang mengisi laporan pada periode ini</small>
        </div>
         <div class="px-4 pb-3">
    <div class="btn-group btn-group-sm" role="group">
        <a href="?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&petugas_id=<?= $filter_petugas ?>&tipe=semua"
           class="btn <?= $filter_tipe === 'semua' ? 'btn-green' : 'btn-outline-success' ?>">
            Tampilkan Semua
        </a>
        <a href="?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&petugas_id=<?= $filter_petugas ?>&tipe=petugas"
           class="btn <?= $filter_tipe === 'petugas' ? 'btn-green' : 'btn-outline-success' ?>">
            Hanya Petugas
        </a>
    </div>
</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-green">
                        <tr>
                            <th class="text-center">NO</th>
                            <th>NAMA PETUGAS</th>
                            <th>TANGGAL TERAKHIR INPUT</th>
                            <th class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; foreach($laporan as $l): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-center"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($l['nama_petugas']) ?></div>
                            </td>
                            <td class="fw-semibold text-muted">
                                <i class="bi bi-calendar-check me-1"></i> <?= date('d/m/Y', strtotime($l['tgl_terakhir'])) ?>
                            </td>
                            <td class="text-center">
                                <a href="monitoring_atasan_detail.php?id_pegawai=<?= $l['pegawai_id'] ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&jenis=rencana" 
                                   class="btn btn-detail btn-sm">
                                    <i class="bi bi-search me-1"></i> Buka Laporan
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if(empty($laporan)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-info-circle h2 d-block mb-2 opacity-25"></i>
                                Tidak ada aktivitas petugas ditemukan pada rentang tanggal ini.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>