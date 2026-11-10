<?php
session_start();

// Tangkap role sebelum session dihancurkan
$role = $_SESSION['role'] ?? '';

session_destroy();

// Redirect berdasarkan role
if ($role === 'direksi' || $role === 'audit_internal') {
    header("Location: loginkhusus.php");
} else {
    header("Location: login.php");
}
exit;