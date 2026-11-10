<?php
session_start();

// 1. Proteksi Session: Cek apakah user sudah login
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// 2. Ambil parameter dan ID dari Session
$id         = $_GET['id'] ?? '';
$jenis      = $_GET['jenis'] ?? '';
$id_pegawai = $_SESSION['pegawai_id']; // Mengambil ID dari session agar aman

if ($id && $jenis) {
    // Mapping jenis ke nama tabel
    $tables = [
        'rencana'    => 'trx_rencana_kegiatan',
        'prospek'    => 'trx_prospek_ao',
        'kunjungan'  => 'trx_kunjungan_ao',
        'funding'    => 'trx_prospek_funding',
        'collection' => 'trx_collection_funding',
        'analisa'    => 'trx_analisa_kredit'
    ];

    // Cek apakah jenis ada dalam daftar mapping
    if (!array_key_exists($jenis, $tables)) {
        die("Jenis laporan tidak valid.");
    }

    $table_name = $tables[$jenis];

    try {
        /**
         * 3. Eksekusi Hapus dengan validasi kepemilikan data
         * Menambahkan AND pegawai_id = ? untuk memastikan 
         * hanya pemilik data yang bisa menghapus.
         */
        $stmt = $conn->prepare("DELETE FROM $table_name WHERE id = ? AND pegawai_id = ?");
        $stmt->execute([$id, $id_pegawai]);
        
        // 4. Kembali ke halaman monitoring dengan membawa parameter jenis yang sama
        header("Location: monitoring_petugas.php?id_pegawai=$id_pegawai&jenis=$jenis&pesan=deleted");
        exit;
    } catch(PDOException $e) {
        // Jika gagal, tampilkan pesan error
        die("Gagal menghapus data: " . $e->getMessage());
    }
} else {
    // Jika akses langsung tanpa parameter
    header("Location: monitoring_petugas.php");
    exit;
}
?>