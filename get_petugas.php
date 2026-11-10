<?php
include 'koneksi.php';

$q = isset($_GET['q']) ? $_GET['q'] : '';

$sql = "SELECT id, nama, posisi, kantor FROM pegawai 
        WHERE nama LIKE :key OR kantor LIKE :key LIMIT 15";
$stmt = $conn->prepare($sql);
$stmt->execute(['key' => "%$q%"]);
$rows = $stmt->fetchAll();

if ($rows) {
    foreach ($rows as $row) {
        echo '
        <a href="dashboard_petugas.php?id='.$row['id'].'" class="list-group-item list-group-item-action">
            <div class="fw-bold">'.$row['nama'].'</div>
            <small class="text-muted">'.$row['posisi'].' - '.$row['kantor'].'</small>
        </a>';
    }
} else {
    echo '<div class="list-group-item text-center text-muted">Nama tidak ditemukan</div>';
}
?>