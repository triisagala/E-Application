<?php
require_once "auth.php";
wajibLogin(["wisatawan"]);

$st = $pdo->prepare(
    "SELECT o.*, d.name AS dname, s.start_time, COALESCE(qc.last_called,0) AS dipanggil
     FROM orders o
     JOIN sessions s ON s.id = o.session_id
     JOIN destinations d ON d.id = s.destination_id
     LEFT JOIN queue_calls qc ON qc.session_id = o.session_id AND qc.visit_date = o.visit_date
     WHERE o.user_id = ? AND (o.status = 'paid' OR (o.status = 'pending' AND o.expires_at > NOW()))
     ORDER BY o.id DESC"
);
$st->execute([user()["id"]]);
$pesanan = $st->fetchAll();

$tq = $pdo->prepare("SELECT * FROM tickets WHERE order_id = ? ORDER BY id");
$baru = $_GET["baru"] ?? "";

$judul = "Tiket saya";
require_once "header.php";
?>
<h2>Tiket saya</h2>
<?php if ($baru): ?><p class="alert ok">Pembayaran berhasil. E-tiket dan nomor antrean sudah terbit.</p><?php endif; ?>

<?php if (!$pesanan): ?>
  <div class="card in"><p>Belum ada tiket. <a href="index.php">Lihat destinasi</a></p></div>
<?php endif; ?>

<?php foreach ($pesanan as $o): ?>
  <?php if ($o["status"] === "pending"): ?>
    <div class="card in">
      <b><?= e($o["dname"]) ?></b> <span class="mute">(<?= e($o["order_code"]) ?>)</span><br>
      <span class="mute"><?= e(tgl($o["visit_date"])) ?>, sesi <?= jam($o["start_time"]) ?></span>
      <p class="alert">Menunggu pembayaran. <a href="bayar.php?kode=<?= e($o["order_code"]) ?>">Bayar sekarang</a></p>
    </div>
    <?php continue; ?>
  <?php endif; ?>

  <?php $di_depan = max(0, $o["queue_number"] - $o["dipanggil"] - 1); ?>
  <div class="antrean">
    <div>
      <span class="mute-w">Nomor antrean, sesi <?= jam($o["start_time"]) ?>, <?= e(tgl($o["visit_date"])) ?></span>
      <div class="nomor"><?= sprintf("%03d", $o["queue_number"]) ?></div>
    </div>
    <div class="kanan">
      <?= $o["dipanggil"] >= $o["queue_number"] ? "Giliran Anda sudah dipanggil" : $di_depan . " antrean di depan Anda" ?><br>
      <span class="mute-w">Sedang dipanggil: <?= $o["dipanggil"] ? sprintf("%03d", $o["dipanggil"]) : "belum ada" ?></span>
    </div>
  </div>

  <?php $tq->execute([$o["id"]]); foreach ($tq->fetchAll() as $t): ?>
    <div class="tiket <?= $t["status"] === "used" ? "used" : "" ?>">
      <div class="utama">
        <b><?= e($o["dname"]) ?></b><br>
        <span class="mute"><?= e(tgl($o["visit_date"])) ?>, sesi <?= jam($o["start_time"]) ?></span><br>
        <code><?= e($t["ticket_code"]) ?></code><br>
        <b><?= $t["status"] === "used" ? "Sudah dipakai " . e(date("H:i", strtotime($t["used_at"]))) : "Berlaku" ?></b>
      </div>
      <div class="stub"><div class="qr" data-kode="<?= e($t["ticket_code"]) ?>"></div></div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php
$skrip = '<script src="js/qrcode.min.js"></script>
<script>document.querySelectorAll("[data-kode]").forEach(function(el){new QRCode(el,{text:el.dataset.kode,width:112,height:112});});</script>';
require_once "footer.php";
