<?php
require_once "auth.php";

$id = (int)($_GET["id"] ?? 0);
$st = $pdo->prepare("SELECT * FROM destinations WHERE id = ? AND is_active = 1");
$st->execute([$id]);
$w = $st->fetch();
if (!$w) { http_response_code(404); die("Destinasi tidak ditemukan. <a href='index.php'>Kembali</a>"); }

$hariIni = date("Y-m-d");
$maks    = date("Y-m-d", strtotime("+30 days"));
$tanggal = $_GET["tanggal"] ?? "";
$pesan   = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]);
$sesi    = [];

if ($tanggal !== "") {
    $d = DateTime::createFromFormat("Y-m-d", $tanggal);
    if (!$d || $d->format("Y-m-d") !== $tanggal || $tanggal < $hariIni || $tanggal > $maks) {
        $pesan = "Tanggal harus dalam 30 hari ke depan.";
        $tanggal = "";
    } elseif ($w["closed_day"] !== null && (int)$d->format("w") === (int)$w["closed_day"]) {
        $pesan = $w["name"] . " tutup pada hari " . namaHari($w["closed_day"]) . ". Pilih tanggal lain.";
        $tanggal = "";
    } else {
        $q = $pdo->prepare(
            "SELECT s.id, s.start_time, s.quota,
                    s.quota - COALESCE((SELECT SUM(o.qty) FROM orders o
                        WHERE o.session_id = s.id AND o.visit_date = ?
                          AND (o.status='paid' OR (o.status='pending' AND o.expires_at > NOW()))), 0) AS sisa
             FROM sessions s WHERE s.destination_id = ? ORDER BY s.start_time"
        );
        $q->execute([$tanggal, $id]);
        $sesi = $q->fetchAll();
    }
}

$judul = $w["name"];
require_once "header.php";
?>
<p><a href="index.php">&larr; Kembali ke daftar</a></p>
<div class="card">
  <?php if ($w["image"]): ?>
    <img class="pic" src="img/<?= e($w["image"]) ?>" alt="<?= e($w["name"]) ?>">
  <?php else: ?>
    <div class="pic ph"><?= e($w["name"]) ?></div>
  <?php endif; ?>
  <div class="in">
    <span class="tag kat-<?= e(strtolower($w["category"])) ?>"><?= e($w["category"]) ?></span>
    <h2><?= e($w["name"]) ?></h2>
    <p><?= e($w["description"]) ?></p>
    <p class="mute">
      Jam buka: <?= jam($w["open_time"]) ?> – <?= jam($w["close_time"]) ?>
      <?php if ($w["closed_day"] !== null): ?>(tutup <?= namaHari($w["closed_day"]) ?>)<?php endif; ?>
    </p>
    <p class="harga"><?= rp($w["price"]) ?> <small>/ tiket</small></p>

    <?php if ($pesan): ?><p class="alert err"><?= e($pesan) ?></p><?php endif; ?>

    <h3>1. Pilih tanggal kunjungan</h3>
    <form method="get">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="date" name="tanggal" min="<?= $hariIni ?>" max="<?= $maks ?>" value="<?= e($tanggal) ?>" required>
      <button class="btn" type="submit">Lihat sesi</button>
    </form>

    <?php if ($sesi): ?>
      <h3>2. Pilih sesi dan jumlah tiket</h3>
      <p class="mute"><?= e(tgl($tanggal)) ?></p>
      <?php if (!user()): ?>
        <p class="alert">Silakan <a href="login.php?next=<?= urlencode($_SERVER["REQUEST_URI"]) ?>">masuk</a> atau <a href="daftar.php">daftar</a> untuk memesan.</p>
      <?php elseif (user()["role"] !== "wisatawan"): ?>
        <p class="alert">Hanya akun wisatawan yang dapat memesan tiket.</p>
      <?php endif; ?>
      <form method="post" action="pesan.php">
        <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">
        <div class="chips">
          <?php foreach ($sesi as $s): $habis = $s["sisa"] <= 0; ?>
            <label class="chip <?= $habis ? "off" : "" ?>">
              <input type="radio" name="sesi" value="<?= (int)$s["id"] ?>" <?= $habis ? "disabled" : "required" ?>>
              <?= jam($s["start_time"]) ?> <small>sisa <?= max(0, (int)$s["sisa"]) ?></small>
            </label>
          <?php endforeach; ?>
        </div>
        <label>Jumlah tiket (maks. 10)
          <input type="number" name="qty" min="1" max="10" value="1" required>
        </label>
        <button class="btn" type="submit" <?= (user() && user()["role"] === "wisatawan") ? "" : "disabled" ?>>Lanjut ke pembayaran</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require_once "footer.php"; ?>
