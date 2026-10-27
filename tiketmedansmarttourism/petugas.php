<?php
require_once "auth.php";
wajibLogin(["petugas", "admin"]);

$hari  = date("Y-m-d");
$hasil = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "scan") {
        $kode = strtoupper(trim($_POST["kode"] ?? ""));
        $st = $pdo->prepare(
            "SELECT t.*, o.visit_date, d.name AS dname, s.start_time
             FROM tickets t JOIN orders o ON o.id = t.order_id
             JOIN sessions s ON s.id = o.session_id JOIN destinations d ON d.id = s.destination_id
             WHERE t.ticket_code = ?"
        );
        $st->execute([$kode]);
        $t = $st->fetch();

        if (!$t) {
            $r = "invalid";      $pesan = "Kode tidak dikenali. Tiket ditolak.";
        } elseif ($t["status"] === "used") {
            $r = "already_used"; $pesan = "Tiket sudah dipakai pukul " . date("H:i", strtotime($t["used_at"])) . ". Tiket ditolak.";
        } elseif ($t["visit_date"] !== $hari) {
            $r = "wrong_date";   $pesan = "Tiket berlaku untuk " . tgl($t["visit_date"]) . ", bukan hari ini.";
        } else {
            // hanya berhasil jika status masih 'valid' (mencegah pemakaian ganda)
            $up = $pdo->prepare("UPDATE tickets SET status='used', used_at=NOW() WHERE id=? AND status='valid'");
            $up->execute([$t["id"]]);
            if ($up->rowCount() === 1) {
                $r = "success";  $pesan = "Check-in berhasil: " . $t["dname"] . ", sesi " . jam($t["start_time"]) . ".";
            } else {
                $r = "already_used"; $pesan = "Tiket sudah dipakai. Tiket ditolak.";
            }
        }
        $pdo->prepare("INSERT INTO checkins (ticket_code, ticket_id, officer_id, result) VALUES (?,?,?,?)")
            ->execute([$kode, $t["id"] ?? null, user()["id"], $r]);
        $hasil = ["ok" => $r === "success", "pesan" => $pesan];
    }

    if ($aksi === "panggil") {
        $sid = (int)($_POST["sesi"] ?? 0);
        $pdo->prepare(
            "UPDATE queue_calls SET last_called = last_called + 1
             WHERE session_id = ? AND visit_date = ?
               AND last_called < (SELECT COALESCE(MAX(queue_number),0) FROM orders
                                  WHERE session_id = ? AND visit_date = ? AND status = 'paid')"
        )->execute([$sid, $hari, $sid, $hari]);
        pindah("petugas.php");
    }
}

$antrean = $pdo->prepare(
    "SELECT s.id AS sid, d.name, s.start_time, MAX(o.queue_number) AS terakhir, COALESCE(qc.last_called,0) AS dipanggil
     FROM orders o JOIN sessions s ON s.id = o.session_id JOIN destinations d ON d.id = s.destination_id
     LEFT JOIN queue_calls qc ON qc.session_id = s.id AND qc.visit_date = o.visit_date
     WHERE o.visit_date = ? AND o.status = 'paid'
     GROUP BY s.id, d.name, s.start_time, qc.last_called
     ORDER BY d.name, s.start_time"
);
$antrean->execute([$hari]);
$antrean = $antrean->fetchAll();

$tunggu = $pdo->prepare(
    "SELECT t.ticket_code FROM tickets t JOIN orders o ON o.id = t.order_id
     WHERE t.status = 'valid' AND o.visit_date = ? AND o.status = 'paid' ORDER BY t.id DESC LIMIT 8"
);
$tunggu->execute([$hari]);
$tunggu = $tunggu->fetchAll();

$judul = "Petugas";
require_once "header.php";
?>
<h2>Panel petugas <small class="mute"><?= e(tgl($hari)) ?></small></h2>
<div class="grid2">
  <section class="card in">
    <h3>1. Periksa tiket di pintu masuk</h3>
    <form method="post" id="fscan">
      <input type="hidden" name="aksi" value="scan">
      <input type="text" name="kode" id="kode" placeholder="MST-XXXXXXXX" autocomplete="off" required>
      <button class="btn" type="submit">Pindai</button>
    </form>
    <?php if ($hasil): ?><p class="hasil <?= $hasil["ok"] ? "ok" : "err" ?>"><?= e($hasil["pesan"]) ?></p><?php endif; ?>
    <?php if ($tunggu): ?>
      <div class="chips">
        <?php foreach ($tunggu as $t): ?>
          <button type="button" class="chip" onclick="document.getElementById('kode').value='<?= e($t["ticket_code"]) ?>';document.getElementById('fscan').submit();"><?= e($t["ticket_code"]) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card in">
    <h3>2. Panggil antrean</h3>
    <?php if (!$antrean): ?>
      <p class="mute">Belum ada pesanan lunas untuk hari ini.</p>
    <?php endif; ?>
    <?php foreach ($antrean as $a): ?>
      <div class="baris">
        <span><b><?= e($a["name"]) ?></b><br>
          <span class="mute">Sesi <?= jam($a["start_time"]) ?>, dipanggil <?= $a["dipanggil"] ? sprintf("%03d", $a["dipanggil"]) : "-" ?> dari <?= sprintf("%03d", $a["terakhir"]) ?></span></span>
        <form method="post">
          <input type="hidden" name="aksi" value="panggil">
          <input type="hidden" name="sesi" value="<?= (int)$a["sid"] ?>">
          <button class="btn" type="submit" <?= $a["dipanggil"] >= $a["terakhir"] ? "disabled" : "" ?>>Panggil berikutnya</button>
        </form>
      </div>
    <?php endforeach; ?>
  </section>
</div>
<?php require_once "footer.php"; ?>
