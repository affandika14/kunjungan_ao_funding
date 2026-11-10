<?php
session_start();

$id_pegawai = $_GET['id']   ?? '';
$nama       = $_GET['nama'] ?? '';
$role       = $_GET['role'] ?? '';

if (!empty($id_pegawai) && !empty($role)) {

    $_SESSION['is_login']   = true;
    $_SESSION['pegawai_id'] = $id_pegawai;
    $_SESSION['nama_user']  = urldecode($nama);
    $_SESSION['role']       = $role;

    switch ($role) {
        case 'admin':
            header("Location: admin_manage_users.php");
            break;
        case 'direksi':
            header("Location: dashboard_direksi.php");
            break;
        case 'audit_internal':
            header("Location: dashboard_direksi.php");
            break;
        case 'atasan':
            header("Location: dashboard_atasan.php");
            break;
        default:
            header("Location: dashboard_form_kegiatan.php");
            break;
    }
    exit;

} else {
    header("Location: login.php?pesan=error_session");
    exit;
}