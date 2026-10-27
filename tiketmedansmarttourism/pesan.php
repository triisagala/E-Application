<?php
require_once "auth.php";
wajibLogin(["wisatawan"]);
if ($_SERVER["REQUEST_METHOD"] !== "POST") pindah("index.php");

$sid = (int)($_POST["sesi"] ?? 0);
$tgl = $_POST["tanggal"] ?? "";
$qty = (int)($_POST["qty"] ?? 0);

$st = $pdo->prepare(
    "SELECT s.id, d.id AS did, d.price, d.closed_day
     FROM sessions s JOIN destinations d ON d.id = s.destination_id
     WHERE s.id = ? AND d.is_active = 1"
);
$st->execute([$sid]);
$s = $st->fetch();
if (!$s) pindah("index.php");

$balik = "detail.php?id=" . $s["did"] . "&tanggal=" . urlencode($tgl);

try {
    $d = DateTime::createFromFormat("Y-m-d", $tgl);
    if (!$d || $d->format("Y-m-d") !== $tgl) throw new Exception("Tanggal tidak valid.");
    if ($tgl < date("Y-m-d") || $tgl > date("Y-m-d", strtotime("+30 days"))) throw new Exception("Tanggal harus dalam 30 hari ke depan.");
    if ($s["closed_day"] !== null && (int)$d->format("w") === (int)$s["closed_day"]) throw new Exception("Destinasi tutup pada hari itu.");
    if ($qty < 1 || $qty > 10) throw new Exception("Jumlah tiket harus 1 sampai 10.");

    $pdo->beginTransaction();
    // kunci baris sesi supaya dua pemesan tidak merebut kuota terakhir bersamaan
    $pdo->prepare("SELECT id FROM sessions WHERE id = ? FOR UPDATE")->execute([$sid]);

    $sisa = sisaKuota($pdo, $sid, $tgl);
    if ($qty > $sisa) throw new Exception("Kuota sesi tidak cukup. Sisa kuota: $sisa.");

    $subtotal = $s["price"] * $qty;
    $fee      = 1000;
    $kode     = "ORD-" . kodeAcak(6);

    $pdo->prepare(
        "INSERT INTO orders (order_code,user_id,session_id,visit_date,qty,subtotal,fee,total,status,expires_at)
         VALUES (?,?,?,?,?,?,?,?, 'pending', DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
    )->execute([$kode, user()["id"], $sid, $tgl, $qty, $subtotal, $fee, $subtotal + $fee]);

    $pdo->commit();
    pindah("bayar.php?kode=" . $kode);
} catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION["flash"] = $ex->getMessage();
    pindah($balik);
}
