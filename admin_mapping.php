<?php
include 'koneksi.php';

// --- LOGIKA PEMROSESAN DATA ---
if (isset($_POST['simpan_mapping'])) {
    $id_ao = $_POST['id_ao'];
    $atasans = [
        'Kas'    => $_POST['kepala_kas'], 
        'Bagian' => $_POST['kepala_bagian'], 
        'Cabang' => $_POST['kepala_cabang'],
        'Wakil'  => $_POST['wakil_kacab']
    ];

    try {
        // Mulai transaksi database
        $conn->beginTransaction();

        // 1. Hapus mapping lama AO ini agar tidak tumpang tindih
        $conn->prepare("DELETE FROM mapping_atasan WHERE id_bawahan = ?")->execute([$id_ao]);

        // 2. Simpan tiap atasan jika dipilih (tidak kosong)
        $stmt = $conn->prepare("INSERT INTO mapping_atasan (id_bawahan, id_atasan, level_atasan) VALUES (?, ?, ?)");
        
        foreach ($atasans as $level => $id_atasan) {
            if (!empty($id_atasan)) {
                $stmt->execute([$id_ao, $id_atasan, $level]);
            }
        }

        $conn->commit();
        $notif = "<div class='alert alert-success shadow-sm border-0 animate__animated animate__fadeIn'>Struktur Hierarki berhasil disimpan dan diperbarui!</div>";
    } catch (Exception $e) {
        $conn->rollBack();
        $notif = "<div class='alert alert-danger shadow-sm border-0'>Error: " . $e->getMessage() . "</div>";
    }
}

// Ambil list Pegawai untuk dropdown
$semua_pegawai = $conn->query("SELECT id, nama, posisi FROM pegawai ORDER BY nama ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Mapping Struktur Atasan Hijau</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { 
            background-color: #f0f7f4; 
            font-family: 'Inter', sans-serif; 
        }
        .card-custom { 
            border: none; 
            border-radius: 15px; 
            overflow: hidden; 
        }
        .card-header-green {
            background: linear-gradient(135deg, #198754 0%, #0d5a36 100%);
            color: white;
        }
        .section-ao { 
            background-color: #ffffff; 
            border-radius: 12px; 
            padding: 25px; 
            border: 2px dashed #198754; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .form-label { 
            font-weight: 700; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            color: #146c43; 
        }
        
        /* Select2 Custom Styling to match Hijau Theme */
        .select2-container--default .select2-selection--single {
            height: 45px !important;
            padding: 8px !important;
            border: 1px solid #ced4da !important;
            border-radius: 0.375rem !important;
        }
        .select2-container--default .select2-selection--single:focus {
            border-color: #198754 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px !important;
        }
        .btn-primary-green {
            background-color: #198754;
            border: none;
            padding: 12px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-primary-green:hover {
            background-color: #0d5a36;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }
        .badge-kas { background-color: #198754; }
        .badge-bagian { background-color: #146c43; }
        .badge-cabang { background-color: #0d5a36; }
        .badge-wakil { background-color: #06331d; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            
            <?php echo $notif ?? ''; ?>

            <div class="card card-custom shadow-lg mb-5">
                <div class="card-header card-header-green p-4 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill me-2"></i> SETTING HIERARKI & MAPPING ATASAN</h4>
                    <a href="dashboard_admin.php" class="btn btn-outline-light btn-sm px-3 rounded-pill">Kembali</a>
                </div>
                <div class="card-body p-5 bg-white">
                    <form method="POST">
                        
                        <div class="section-ao mb-5">
                            <label class="form-label"><i class="bi bi-person-badge-fill me-1"></i> PILIH PETUGAS / AO MARKETING :</label>
                            <select name="id_ao" class="form-select select2" required>
                                <option value="">-- Cari Nama Petugas --</option>
                                <?php foreach($semua_pegawai as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama']) ?> (<?= $p['posisi'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mt-2 text-success small"><i class="bi bi-info-circle"></i> Sistem akan memperbarui seluruh tingkatan atasan untuk nama yang dipilih.</div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-3">
                                <label class="form-label">1. Kepala Kas</label>
                                <select name="kepala_kas" class="form-select select2">
                                    <option value="">-- Tidak Ada --</option>
                                    <?php foreach($semua_pegawai as $p) echo "<option value='{$p['id']}'>".htmlspecialchars($p['nama'])."</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">2. Kepala Bagian</label>
                                <select name="kepala_bagian" class="form-select select2">
                                    <option value="">-- Tidak Ada --</option>
                                    <?php foreach($semua_pegawai as $p) echo "<option value='{$p['id']}'>".htmlspecialchars($p['nama'])."</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">3. Kepala Cabang</label>
                                <select name="kepala_cabang" class="form-select select2">
                                    <option value="">-- Tidak Ada --</option>
                                    <?php foreach($semua_pegawai as $p) echo "<option value='{$p['id']}'>".htmlspecialchars($p['nama'])."</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">4. Wakil Kepala Cabang</label>
                                <select name="wakil_kacab" class="form-select select2">
                                    <option value="">-- Tidak Ada --</option>
                                    <?php foreach($semua_pegawai as $p) echo "<option value='{$p['id']}'>".htmlspecialchars($p['nama'])."</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-5">
                            <button type="submit" name="simpan_mapping" class="btn btn-primary-green btn-lg w-100 text-white shadow-sm rounded-3">
                                <i class="bi bi-cloud-arrow-up-fill me-2"></i> SIMPAN STRUKTUR HIERARKI SEKARANG
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <div class="card card-custom shadow-sm p-4 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-1"></i> Data Mapping Terakhir (10 Data Terbaru)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm border align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="py-2 px-3">Nama AO</th>
                                <th class="py-2">Hierarki Atasan (Level: Nama)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query diperbaiki agar kompatibel dengan ONLY_FULL_GROUP_BY
                            $show_mapping = $conn->query("
                                SELECT 
                                    b.nama as nama_ao, 
                                    GROUP_CONCAT(CONCAT(m.level_atasan, ': ', a.nama) ORDER BY m.id ASC SEPARATOR ' | ') as list_atasan 
                                FROM mapping_atasan m
                                JOIN pegawai a ON m.id_atasan = a.id
                                JOIN pegawai b ON m.id_bawahan = b.id
                                GROUP BY b.id, b.nama
                                ORDER BY MAX(m.id) DESC 
                                LIMIT 10
                            ")->fetchAll();

                            foreach($show_mapping as $sm): ?>
                            <tr>
                                <td class="fw-bold text-success py-2 px-3"><?= htmlspecialchars($sm['nama_ao']) ?></td>
                                <td class="small text-muted">
                                    <?php 
                                        $parts = explode(' | ', $sm['list_atasan']);
                                        foreach($parts as $pPart){
                                            echo "<span class='badge bg-light text-dark border me-1'>" . htmlspecialchars($pPart) . "</span>";
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(!$show_mapping): ?>
                                <tr><td colspan="2" class="text-center py-5 text-muted italic">Belum ada data mapping terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        // Inisialisasi Select2 dengan tema kustom
        $('.select2').select2({
            placeholder: "-- Cari & Pilih Nama Pegawai --",
            allowClear: true,
            width: '100%'
        });
    });
</script>

</body>
</html>