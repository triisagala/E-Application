<?php
$judul = "Medan Smart Tourism";
require_once "header.php";

$wisata = $pdo->query("SELECT * FROM destinations WHERE is_active = 1 ORDER BY id")->fetchAll();
?>
<section class="hero">
  <h2>Jelajahi Medan tanpa antre panjang</h2>
  <p>Pesan tiket, ambil nomor antrean, dan masuk cukup dengan QR. Semua dari ponsel Anda.</p>
  <div class="lencana"><span>Tiket QR</span><span>Antrean digital</span><span>Bayar online</span></div>
</section>
<h2>Pilih destinasi</h2>
<div class="grid">
  <?php foreach ($wisata as $w): ?>
    <article class="card">
      <?php if ($w["image"]): ?>
        <img class="pic" src="img/<?= e($w["image"]) ?>" alt="<?= e($w["name"]) ?>">
      <?php else: ?>
        <div class="pic ph"><?= e($w["name"]) ?></div>
      <?php endif; ?>
      <div class="in">
        <span class="tag kat-<?= e(strtolower($w["category"])) ?>"><?= e($w["category"]) ?></span>
        <h3><?= e($w["name"]) ?></h3>
        <p class="mute"><?= e($w["description"]) ?></p>
        <p class="mute">
          Jam buka: <?= jam($w["open_time"]) ?> – <?= jam($w["close_time"]) ?>
          <?php if ($w["closed_day"] !== null): ?>(tutup <?= namaHari($w["closed_day"]) ?>)<?php endif; ?>
        </p>
        <p class="harga"><?= rp($w["price"]) ?> <small>/ tiket</small></p>
        <a class="btn" href="detail.php?id=<?= (int)$w["id"] ?>">Lihat &amp; pesan</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php require_once "footer.php"; ?>
