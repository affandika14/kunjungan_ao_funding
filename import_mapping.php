<?php
include 'koneksi.php';

// 1. Buka file CSV
$file_path = 'mapppingnew.csv';
if (!file_exists($file_path)) {
    die("File $file_path tidak ditemukan!");
}

$file = fopen($file_path, 'r');
// Ambil header dan pastikan menggunakan delimiter yang sesuai (;)
$header = fgetcsv($file, 1000, ";"); 

$count = 0;
$error_names = [];

/**
 * Fungsi bantu untuk mencari ID Pegawai berdasarkan Nama
 * Menggunakan pencarian LIKE agar lebih fleksibel terhadap spasi
 */
function getPegawaiId($conn, $nama) {
    if (empty(trim($nama))) return null;
    $stmt = $conn->prepare("SELECT id FROM pegawai WHERE nama LIKE ? LIMIT 1");
    $stmt->execute(["%" . trim($nama) . "%"]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

// 2. Loop baca baris demi baris
while (($row = fgetcsv($file, 1000, ";")) !== FALSE) {
    // Mapping index berdasarkan struktur CSV terbaru:
    // 0: AO
    // 1: Kepala Kas
    // 2: Kepala Bagian
    // 3: Kepala Cabang
    // 4: Wakil Kepala Cabang
    
    $nama_ao      = $row[0];
    $nama_kas     = $row[1] ?? '';
    $nama_bagian  = $row[2] ?? '';
    $nama_cabang  = $row[3] ?? '';
    $nama_wakil   = $row[4] ?? ''; // Kolom baru untuk Wakil

    // Cari ID si AO (Bawahan)
    $id_ao = getPegawaiId($conn, $nama_ao);
    
    if ($id_ao) {
        // Daftar Atasan yang akan diproses ke dalam tabel mapping_atasan
        $mapping_list = [
            ['nama' => $nama_kas,    'level' => 'Kas'],
            ['nama' => $nama_bagian, 'level' => 'Bagian'],
            ['nama' => $nama_cabang, 'level' => 'Cabang'],
            ['nama' => $nama_wakil,  'level' => 'Wakil'] // Support Wakil Kepala Cabang
        ];

        foreach ($mapping_list as $m) {
            $nama_atasan_clean = trim($m['nama']);
            
            if (!empty($nama_atasan_clean)) {
                $id_atasan = getPegawaiId($conn, $m['nama']);
                
                if ($id_atasan) {
                    // Cek apakah hubungan mapping ini sudah ada (mencegah duplikat)
                    $cek = $conn->prepare("SELECT id FROM mapping_atasan WHERE id_bawahan = ? AND id_atasan = ? AND level_atasan = ?");
                    $cek->execute([$id_ao, $id_atasan, $m['level']]);
                    
                    if (!$cek->fetch()) {
                        // Masukkan ke tabel mapping_atasan
                        $ins = $conn->prepare("INSERT INTO mapping_atasan (id_bawahan, id_atasan, level_atasan) VALUES (?, ?, ?)");
                        $ins->execute([$id_ao, $id_atasan, $m['level']]);
                        $count++;
                    }
                } else {
                    // Jika nama atasan ada di CSV tapi tidak ketemu di tabel pegawai
                    $error_names[] = "Atasan tidak ditemukan: " . $m['nama'];
                }
            }
        }
    } else {
        // Jika nama AO tidak ketemu di tabel pegawai
        $error_names[] = "Petugas AO tidak ditemukan: " . $nama_ao;
    }
}

fclose($file);

echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
echo "<h2 style='color: #2c3e50;'>Proses Import Mapping Selesai!</h2>";
echo "<p style='font-size: 16px;'>Total <strong>$count</strong> hubungan hierarki (termasuk Wakil Kepala Cabang) berhasil diproses.</p>";

if (!empty($error_names)) {
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba;'>";
    echo "<h4>Catatan / Error (" . count(array_unique($error_names)) . " data):</h4>";
    echo "<p>Nama-nama berikut ada di CSV tetapi tidak terdaftar di tabel <strong>pegawai</strong>. Pastikan ejaan nama sama persis.</p>";
    echo "<ul><li>" . implode("</li><li>", array_unique($error_names)) . "</li></ul>";
    echo "</div>";
}
echo "</div>";
?>