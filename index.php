<?php
// header("Location: maintenance.php");
// exit;

session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Pilih Petugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #f0f7f4; /* Hijau sangat muda untuk background */
            font-family: 'Inter', sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        /* Header dengan Gradasi Hijau */
        .card-header-green {
            background: linear-gradient(135deg, #198754 0%, #0d5a36 100%);
            color: white;
            border-bottom: none;
        }

        /* Input Search Highlight Hijau */
        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }

        /* Custom Scrollbar Hijau */
        #daftar_petugas::-webkit-scrollbar {
            width: 6px;
        }
        #daftar_petugas::-webkit-scrollbar-thumb {
            background: #198754;
            border-radius: 10px;
        }

        /* Hover pada List Petugas */
        .list-group-item-action:hover {
            background-color: #e9f5ee;
            color: #0d5a36;
            border-left: 4px solid #198754;
            transition: 0.2s;
        }

        .btn-green {
            background-color: #198754;
            color: white;
            border-radius: 10px;
        }
        
        .btn-green:hover {
            background-color: #146c43;
            color: white;
        }

        .icon-circle {
            width: 40px;
            height: 40px;
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg">
                <div class="card-header card-header-green text-center py-4">
                    <div class="d-flex justify-content-center">
                        <div class="bg-white p-2 rounded-circle mb-2 shadow-sm" style="width: 60px; height: 60px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#198754" class="bi bi-person-check-fill" viewBox="0 0 16 16">
                                <path d="M15.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L12.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                                <path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                            </svg>
                        </div>
                    </div>
                    <h5 class="mb-0 fw-bold">Sistem Laporan Marketing</h5>
                    <small class="opacity-75">Silakan cari dan pilih nama Anda</small>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Cari Nama Anda:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#198754" class="bi bi-search" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                              </svg></span>
                            <input type="text" id="search_petugas" class="form-control border-start-0 ps-0" placeholder="Ketik nama petugas...">
                        </div>
                    </div>
                    
                    <div id="daftar_petugas" class="list-group list-group-flush border rounded-3" style="max-height: 350px; overflow-y: auto;">
                        </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted italic">Versi Aplikasi 2.0 &bull; BPR Hijau</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const searchInput = document.getElementById('search_petugas');
    const displayArea = document.getElementById('daftar_petugas');

    const cariPetugas = (keyword = '') => {
        displayArea.innerHTML = '<div class="text-center p-3 text-muted">Mencari...</div>';
        fetch(`get_petugas.php?q=${keyword}`)
            .then(res => res.text())
            .then(data => {
                displayArea.innerHTML = data;
            });
    };

    cariPetugas(); // Load awal
    searchInput.addEventListener('keyup', function() {
        cariPetugas(this.value);
    });
</script>
</body>
</html>