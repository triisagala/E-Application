<?php
date_default_timezone_set("Asia/Jakarta");

function e($teks) {                          // cegah XSS saat menampilkan teks
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}
function rp($angka) {                        // 10000 -> Rp10.000
    return "Rp" . number_format((int)$angka, 0, ",", ".");
}
function jam($waktu) {                       // 07:00:00 -> 07:00
    return substr($waktu, 0, 5);
}
function namaHari($n) {
    $hari = ["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
    return $hari[(int)$n] ?? "";
}
function tgl($ymd) {                         // 2026-10-21 -> Rabu, 21 Okt 2026
    $bulan = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
    $t = strtotime($ymd);
    return namaHari(date("w", $t)) . ", " . date("j", $t) . " " . $bulan[date("n", $t) - 1] . " " . date("Y", $t);
}
function kodeAcak($panjang) {                // kode acak tanpa huruf/angka yang mirip
    $h = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $o = "";
    for ($i = 0; $i < $panjang; $i++) $o .= $h[random_int(0, strlen($h) - 1)];
    return $o;
}
function pindah($url) {
    header("Location: " . $url);
    exit;
}
// Sisa kuota sebuah sesi pada tanggal tertentu.
// Dihitung dari pesanan lunas + pesanan menunggu bayar yang belum kedaluwarsa.
function sisaKuota(PDO $pdo, $sessionId, $tgl) {
    $st = $pdo->prepare(
        "SELECT s.quota - COALESCE((
            SELECT SUM(o.qty) FROM orders o
            WHERE o.session_id = s.id AND o.visit_date = ?
              AND (o.status = 'paid' OR (o.status = 'pending' AND o.expires_at > NOW()))
        ), 0) FROM sessions s WHERE s.id = ?"
    );
    $st->execute([$tgl, $sessionId]);
    return (int)$st->fetchColumn();
}
