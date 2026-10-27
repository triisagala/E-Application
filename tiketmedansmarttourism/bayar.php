<?php
require_once "auth.php";
wajibLogin(["wisatawan"]);

$kode = $_GET["kode"] ?? "";
$metode = [
    "Transfer Bank" => [["BCA","Bank Central Asia"],["BNI","Bank Negara Indonesia"],["BRI","Bank Rakyat Indonesia"],["MANDIRI","Bank Mandiri"]],
    "QRIS"          => [["QRIS","QRIS (Quick Response Code)"]],
    "E-Wallet"      => [["GOPAY","GoPay"],["OVO","OVO"],["DANA","DANA"]],
];
$valid = [];
foreach ($metode as $grup) foreach ($grup as $m) $valid[] = $m[0];

function ambilOrder(PDO $pdo, $kode, $uid) {
    $st = $pdo->prepare(
        "SELECT o.*, (o.expires_at IS NOT NULL AND o.expires_at < NOW()) AS kadaluarsa,
                d.name AS dname, d.image, s.start_time
         FROM orders o JOIN sessions s ON s.id = o.session_id JOIN destinations d ON d.id = s.destination_id
         WHERE o.order_code = ? AND o.user_id = ?"
    );
    $st->execute([$kode, $uid]);
    return $st->fetch();
}

$o = ambilOrder($pdo, $kode, user()["id"]);
if (!$o) { http_response_code(404); die("Pesanan tidak ditemukan. <a href='index.php'>Kembali</a>"); }
if ($o["status"] === "paid") pindah("tiket.php");

if ($o["status"] === "pending" && $o["kadaluarsa"]) {
    $pdo->prepare("UPDATE orders SET status='expired' WHERE id=? AND status='pending'")->execute([$o["id"]]);
    $o["status"] = "expired";
}

$err = "";
$info = "";

if ($o["status"] === "pending" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "voucher") {
        $vk = strtoupper(trim($_POST["voucher"] ?? ""));
        if ($vk === "") {
            $pdo->prepare("UPDATE orders SET voucher_code=NULL, discount=0, total=subtotal+fee WHERE id=?")->execute([$o["id"]]);
        } else {
            $st = $pdo->prepare("SELECT * FROM vouchers WHERE code=? AND is_active=1");
            $st->execute([$vk]);
            $v = $st->fetch();
            if (!$v) {
                $err = "Kode voucher tidak ditemukan.";
            } else {
                $disc = $v["percent"] ? (int)round($o["subtotal"] * $v["percent"] / 100) : min((int)$v["amount"], (int)$o["subtotal"]);
                $pdo->prepare("UPDATE orders SET voucher_code=?, discount=?, total=subtotal-?+fee WHERE id=?")
                    ->execute([$vk, $disc, $disc, $o["id"]]);
            }
        }
        $o = ambilOrder($pdo, $kode, user()["id"]);
    }

    if ($aksi === "bayar") {
        $pilih = $_POST["metode"] ?? "";
        if (!in_array($pilih, $valid, true)) {
            $err = "Pilih metode pembayaran dulu.";
        } else {
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare("SELECT * FROM orders WHERE id=? AND status='pending' AND expires_at > NOW() FOR UPDATE");
                $st->execute([$o["id"]]);
                $cur = $st->fetch();
                if (!$cur) throw new Exception("Pesanan sudah tidak berlaku. Silakan pesan ulang.");

                // nomor antrean berikutnya untuk sesi dan tanggal ini
                $st = $pdo->prepare("SELECT COALESCE(MAX(queue_number),0)+1 FROM orders WHERE session_id=? AND visit_date=? FOR UPDATE");
                $st->execute([$cur["session_id"], $cur["visit_date"]]);
                $no = (int)$st->fetchColumn();

                $pdo->prepare("UPDATE orders SET status='paid', pay_method=?, queue_number=?, expires_at=NULL WHERE id=?")
                    ->execute([$pilih, $no, $cur["id"]]);

                $ins = $pdo->prepare("INSERT INTO tickets (order_id, ticket_code) VALUES (?, ?)");
                for ($i = 0; $i < $cur["qty"]; $i++) $ins->execute([$cur["id"], "MST-" . kodeAcak(8)]);

                $pdo->prepare("INSERT IGNORE INTO queue_calls (session_id, visit_date, last_called) VALUES (?,?,0)")
                    ->execute([$cur["session_id"], $cur["visit_date"]]);

                $pdo->commit();
                pindah("tiket.php?baru=" . $kode);
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $err = $ex->getMessage();
            }
        }
    }
}

$judul = "Pembayaran";
require_once "header.php";
?>
<?php if ($o["status"] !== "pending"): ?>
  <div class="card in narrow">
    <h2>Pesanan tidak berlaku</h2>
    <p>Pesanan ini sudah kedaluwarsa atau dibatalkan. Kuota sudah dikembalikan.</p>
    <a class="btn" href="index.php">Pesan ulang</a>
  </div>
<?php else: ?>
  <h2>Pembayaran</h2>
  <?php if ($err): ?><p class="alert err"><?= e($err) ?></p><?php endif; ?>
  <div class="pay">
    <section class="card in">
      <h3>Metode pembayaran (simulasi)</h3>
      <form method="post" id="fbayar">
        <input type="hidden" name="aksi" value="bayar">
        <?php foreach ($metode as $grup => $daftar): ?>
          <fieldset>
            <legend><?= e($grup) ?></legend>
            <?php foreach ($daftar as $m): ?>
              <label class="opt"><input type="radio" name="metode" value="<?= e($m[0]) ?>" required> <b><?= e($m[0]) ?></b> <span class="mute"><?= e($m[1]) ?></span></label>
            <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
      </form>
    </section>

    <aside class="card in">
      <h3>Ringkasan pesanan</h3>
      <p><b><?= e($o["dname"]) ?></b><br>
         <span class="mute"><?= e(tgl($o["visit_date"])) ?>, sesi <?= jam($o["start_time"]) ?><br>
         <?= (int)$o["qty"] ?> tiket</span></p>

      <form method="post" class="inline">
        <input type="hidden" name="aksi" value="voucher">
        <label>Kode voucher
          <input type="text" name="voucher" value="<?= e($o["voucher_code"]) ?>" placeholder="MASUKKAN KODE PROMO">
        </label>
        <button class="btn" type="submit">Gunakan</button>
      </form>

      <div class="baris"><span>Subtotal</span><span><?= rp($o["subtotal"]) ?></span></div>
      <?php if ($o["discount"] > 0): ?><div class="baris"><span>Diskon voucher</span><span>−<?= rp($o["discount"]) ?></span></div><?php endif; ?>
      <div class="baris"><span>Biaya layanan</span><span><?= rp($o["fee"]) ?></span></div>
      <div class="baris total"><span>Total pembayaran</span><span><?= rp($o["total"]) ?></span></div>

      <button class="btn pill" type="submit" form="fbayar">Konfirmasi &amp; Bayar</button>
    </aside>
  </div>
<?php endif; ?>
<?php require_once "footer.php"; ?>
