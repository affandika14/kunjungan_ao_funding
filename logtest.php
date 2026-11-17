<?php
header('Content-Type: text/plain');
echo "log_errors   = " . var_export(ini_get('log_errors'), true) . "\n";
echo "error_log    = " . var_export(ini_get('error_log'), true) . "\n";
echo "display_errors = " . var_export(ini_get('display_errors'), true) . "\n";
echo "php.ini      = " . php_ini_loaded_file() . "\n";
echo "sapi         = " . php_sapi_name() . "\n";
$ok = error_log("[LOGTEST] " . date('c'));
echo "error_log() mengembalikan: " . var_export($ok, true) . "\n";