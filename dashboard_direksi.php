<?php
session_start();

// ============================================================
// AKSES DINAMIS: direksi ATAU audit_internal (AI)
// ============================================================
$allowed_roles = ['direksi', 'audit_internal'];
if (!isset($_SESSION['is_login']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php"); exit;
}

$current_role = $_SESSION['role'];

// Label dinamis berdasarkan jabatan
$role_config = [
    'direksi' => [
        'label'       => 'Direksi',
        'label_short' => 'Direksi',
        'icon'        => 'bi-building',
        'color_start' => '#4a1d96',
        'color_end'   => '#2d1065',
        'badge_bg'    => '#ede9fe',
        'badge_color' => '#4a1d96',
        'btn_color'   => '#4a1d96',
        'btn_hover'   => '#2d1065',
        'login_page'  => 'login.php',
        'pass_page'   => 'ganti_password_direksi.php',
        'pass_label'  => 'Ganti Pass',
    ],
    'audit_internal' => [
        'label'       => 'Audit Internal',
        'label_short' => 'AI',
        'icon'        => 'bi-shield-check',
        'color_start' => '#0f4c81',
        'color_end'   => '#062a4e',
        'badge_bg'    => '#e0f0ff',
        'badge_color' => '#0f4c81',
        'btn_color'   => '#0f4c81',
        'btn_hover'   => '#062a4e',
        'login_page'  => 'loginkhusus.php',
        'pass_page'   => 'ganti_password_direksi.php', // bisa diganti jika ada halaman khusus AI
        'pass_label'  => 'Ganti Pass',
    ],
];

$rc = $role_config[$current_role];

include 'koneksi.php';

$id_user    = $_SESSION['pegawai_id'];
$stmt       = $conn->prepare("SELECT nama, posisi, kantor FROM pegawai WHERE id = ?");
$stmt->execute([$id_user]);
$user_data  = $stmt->fetch();

if (!$user_data) { session_destroy(); header("Location: {$rc['login_page']}"); exit; }

// --- Filter ---
$tgl_mulai      = $_GET['tgl_mulai']   ?? date('Y-m-d');
$tgl_selesai    = $_GET['tgl_selesai'] ?? date('Y-m-d');
$filter_kacab   = $_GET['kacab_id']    ?? '';
$filter_petugas = $_GET['petugas_id']  ?? '';

// ---------------------------------------------------------------
// 1. Daftar Kepala Cabang + kantornya untuk dropdown filter
// ---------------------------------------------------------------
$list_kacab = $conn->query(
    "SELECT p.id, p.nama, p.kantor
     FROM pegawai p
     JOIN users u ON p.id = u.pegawai_id
     WHERE u.status = 'aktif'
     AND p.jabatan LIKE '%Kepala Cabang%'
     ORDER BY p.kantor ASC"
)->fetchAll();

// ---------------------------------------------------------------
// 2. Daftar petugas — tampil semua, atau filter by kantor kepala cabang yg dipilih
// ---------------------------------------------------------------
$kantor_terpilih = '';
if (!empty($filter_kacab)) {
    $stmt_ktr = $conn->prepare("SELECT kantor FROM pegawai WHERE id = ?");
    $stmt_ktr->execute([$filter_kacab]);
    $row_ktr = $stmt_ktr->fetch();
    $kantor_terpilih = $row_ktr['kantor'] ?? '';
}

$sql_petugas = "SELECT p.id, p.nama, p.kantor
                FROM pegawai p
                JOIN users u ON p.id = u.pegawai_id
                WHERE u.status = 'aktif'
                AND p.jabatan NOT LIKE '%Admin%'
                AND p.jabatan NOT LIKE '%Direksi%'
                AND p.jabatan NOT LIKE '%Kepala Cabang%'
                AND p.jabatan NOT LIKE '%Audit%'";
if (!empty($kantor_terpilih)) {
    $sql_petugas .= " AND p.kantor = " . $conn->quote($kantor_terpilih);
}
$sql_petugas .= " ORDER BY p.nama ASC";
$list_petugas = $conn->query($sql_petugas)->fetchAll();

// ---------------------------------------------------------------
// 3. Query aktivitas
// ---------------------------------------------------------------
$tables = [
    ['table' => 'trx_analisa_kredit',    'tgl' => 'tanggal_pengerjaan'],
    ['table' => 'trx_collection_funding','tgl' => 'tanggal'],
    ['table' => 'trx_prospek_funding',   'tgl' => 'tanggal_kunjungan'],
    ['table' => 'trx_prospek_ao',        'tgl' => 'tanggal_kunjungan'],
    ['table' => 'trx_kunjungan_ao',      'tgl' => 'tanggal_kunjungan'],
    ['table' => 'trx_rencana_kegiatan',  'tgl' => 'tanggal']
];
$union_parts = [];
foreach ($tables as $t) {
    $union_parts[] = "SELECT pegawai_id, {$t['tgl']} as tgl_data FROM {$t['table']}";
}
$union_sql = implode(" UNION ALL ", $union_parts);

$where_extra = '';
if (!empty($kantor_terpilih)) {
    $kantor_quoted = $conn->quote($kantor_terpilih);
    $where_extra  .= " AND p.kantor = $kantor_quoted";
}
if (!empty($filter_petugas)) {
    $where_extra .= " AND u.pegawai_id = :petugas";
}

$full_sql = "SELECT p.nama AS nama_petugas, p.kantor, u.pegawai_id,
                    MAX(u.tgl_data) AS tgl_terakhir
             FROM ($union_sql) u
             JOIN pegawai p ON u.pegawai_id = p.id
             WHERE p.jabatan NOT LIKE '%Admin%'
             AND p.jabatan NOT LIKE '%Direksi%'
             AND p.jabatan NOT LIKE '%Kepala Cabang%'
             AND p.jabatan NOT LIKE '%Audit%'
             AND u.tgl_data BETWEEN :tgl1 AND :tgl2
             $where_extra
             GROUP BY u.pegawai_id
             ORDER BY p.kantor, nama_petugas ASC";

$stmt_laporan = $conn->prepare($full_sql);
$stmt_laporan->bindValue(':tgl1', $tgl_mulai);
$stmt_laporan->bindValue(':tgl2', $tgl_selesai);
if (!empty($filter_petugas)) $stmt_laporan->bindValue(':petugas', $filter_petugas);
$stmt_laporan->execute();
$laporan = $stmt_laporan->fetchAll();

// Nama file detail monitoring — sama untuk semua role
$detail_page = 'monitoring_direksi_detail.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?= htmlspecialchars($rc['label']) ?> - BPR Ekadharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f5f3ff; font-family: 'Inter', sans-serif; }
        .navbar-custom {
            background: linear-gradient(135deg, <?= $rc['color_start'] ?> 0%, <?= $rc['color_end'] ?> 100%) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .filter-section {
            background: white; border-radius: 15px; padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .form-label-custom {
            font-weight: 700; color: <?= $rc['btn_color'] ?>;
            font-size: 0.85rem; text-transform: uppercase;
        }
        .btn-primary-role {
            background-color: <?= $rc['btn_color'] ?>; border: none; color: white; font-weight: bold;
        }
        .btn-primary-role:hover { background-color: <?= $rc['btn_hover'] ?>; color: white; }
        .table-card {
            border-radius: 15px; overflow: hidden; border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .thead-role { background-color: <?= $rc['btn_color'] ?>; color: white; }
        .badge-kantor {
            background-color: <?= $rc['badge_bg'] ?>; color: <?= $rc['badge_color'] ?>;
            font-weight: 600; font-size: 0.75rem; padding: 3px 10px; border-radius: 20px;
        }
        .btn-detail {
            border: 1px solid <?= $rc['btn_color'] ?>; color: <?= $rc['btn_color'] ?>;
            font-weight: 600; border-radius: 20px; padding: 2px 15px;
            transition: 0.3s; text-decoration: none;
        }
        .btn-detail:hover { background-color: <?= $rc['btn_color'] ?>; color: white; }
        .row-highlight { background-color: #faf5ff !important; }

        /* Badge role di navbar */
        .badge-role {
            font-size: 0.7rem; padding: 3px 10px; border-radius: 20px;
            background: rgba(255,255,255,0.2); color: white;
            border: 1px solid rgba(255,255,255,0.35); font-weight: 600;
            letter-spacing: 0.5px; text-transform: uppercase;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom shadow mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 d-flex align-items-center gap-2">
            <i class="bi <?= $rc['icon'] ?> me-1"></i>
            Monitoring <?= htmlspecialchars($rc['label']) ?>:
            <strong><?= htmlspecialchars($user_data['nama']) ?></strong>
            <span class="badge-role ms-1"><?= htmlspecialchars($rc['label_short']) ?></span>
        </span>
        <div class="d-flex gap-2">
            <a href="<?= $rc['pass_page'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold"
               style="color:<?= $rc['btn_color'] ?>">
                <i class="bi bi-key-fill"></i> <?= $rc['pass_label'] ?>
            </a>
            <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold"
               onclick="return confirm('Keluar dari sistem?')">
                <i class="bi bi-power"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">

    <!-- Filter -->
    <div class="filter-section mb-4">
        <form method="GET" id="formFilter" class="row g-3 align-items-end">

            <!-- Tanggal -->
            <div class="col-md-3">
                <label class="form-label-custom">Rentang Tanggal</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="tgl_mulai"   class="form-control" value="<?= $tgl_mulai ?>">
                    <span class="input-group-text bg-light text-muted small">s/d</span>
                    <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>">
                </div>
            </div>

            <!-- Filter Kepala Cabang -->
            <div class="col-md-3">
                <label class="form-label-custom">
                    <i class="bi bi-person-badge me-1"></i>Kepala Cabang / Kantor
                </label>
                <select name="kacab_id" id="selKacab" class="form-select form-select-sm"
                        onchange="resetPetugas(); this.form.submit();">
                    <option value="">-- Semua Cabang --</option>
                    <?php foreach($list_kacab as $k): ?>
                        <option value="<?= $k['id'] ?>"
                            <?= $filter_kacab == $k['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama']) ?>
                            &nbsp;·&nbsp;
                            <?= htmlspecialchars($k['kantor']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Nama Petugas -->
            <div class="col-md-3">
                <label class="form-label-custom">
                    <i class="bi bi-people me-1"></i>Nama Petugas
                </label>
                <select name="petugas_id" id="selPetugas" class="form-select form-select-sm">
                    <option value="">-- Semua Petugas<?= !empty($kantor_terpilih) ? ' ('.$kantor_terpilih.')' : '' ?> --</option>
                    <?php foreach($list_petugas as $b): ?>
                        <option value="<?= $b['id'] ?>"
                            <?= $filter_petugas == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary-role btn-sm w-100 shadow-sm">
                    <i class="bi bi-search me-1"></i> LIHAT AKTIVITAS
                </button>
                <?php if (!empty($filter_kacab) || !empty($filter_petugas)): ?>
                <a href="?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>"
                   class="btn btn-outline-secondary btn-sm w-100 mt-1">
                    <i class="bi bi-x-circle me-1"></i> Reset Filter
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Info filter aktif -->
    <?php if (!empty($kantor_terpilih)): ?>
    <div class="alert border-0 mb-3 py-2 px-3 d-flex align-items-center gap-2"
         style="background:<?= $rc['badge_bg'] ?>;color:<?= $rc['badge_color'] ?>;border-radius:10px;">
        <i class="bi bi-funnel-fill"></i>
        <span>Menampilkan petugas dari: <strong><?= htmlspecialchars($kantor_terpilih) ?></strong></span>
    </div>
    <?php endif; ?>

    <!-- Tabel -->
    <div class="card table-card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-people-fill me-2" style="color:<?= $rc['btn_color'] ?>"></i>
                AKTIVITAS PETUGAS
            </h6>
            <small class="text-muted">Total: <strong><?= count($laporan) ?></strong> petugas aktif</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-role">
                        <tr>
                            <th class="text-center" width="50">NO</th>
                            <th>NAMA PETUGAS</th>
                            <th>KANTOR CABANG</th>
                            <th>TERAKHIR INPUT</th>
                            <th class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = 1; foreach($laporan as $l): ?>
                        <tr class="<?= (!empty($kantor_terpilih) && $l['kantor'] === $kantor_terpilih) ? 'row-highlight' : '' ?>">
                            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                            <td class="fw-bold text-uppercase"><?= htmlspecialchars($l['nama_petugas']) ?></td>
                            <td>
                                <span class="badge-kantor">
                                    <i class="bi bi-geo-alt-fill me-1"></i>
                                    <?= htmlspecialchars($l['kantor']) ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <i class="bi bi-calendar-check me-1"></i>
                                <?= date('d/m/Y', strtotime($l['tgl_terakhir'])) ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= $detail_page ?>?id_pegawai=<?= $l['pegawai_id'] ?>&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&jenis=rencana"
                                   class="btn-detail btn-sm">
                                    <i class="bi bi-search me-1"></i> Buka Laporan
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($laporan)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox h2 d-block mb-2 opacity-25"></i>
                                Tidak ada aktivitas ditemukan pada periode ini.
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
<script>
function resetPetugas() {
    document.getElementById('selPetugas').value = '';
}
</script>
</body>
</html>