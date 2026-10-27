<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/koneksi.php";
require_once __DIR__ . "/fungsi.php";

function user() {
    return $_SESSION["user"] ?? null;
}
// Wajib login. $peran = daftar peran yang boleh, kosong = semua peran.
function wajibLogin(array $peran = []) {
    $u = user();
    if (!$u) pindah("login.php?next=" . urlencode($_SERVER["REQUEST_URI"]));
    if ($peran && !in_array($u["role"], $peran, true)) {
        http_response_code(403);
        die("Akses ditolak untuk peran Anda. <a href='index.php'>Kembali</a>");
    }
}
