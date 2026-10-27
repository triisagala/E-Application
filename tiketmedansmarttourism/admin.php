<?php
require_once "auth.php";
wajibLogin(["admin"]);

$hari = date("Y-m-d");

// ---------- pilihan periode, destinasi, dan pembaruan otomatis ----------
$p = $_GET["p"] ?? "7";
if (!in_array($p, ["hari", "7", "30"], true)) $p = "7";
$hariPeriode = ["hari" => 1, "7" => 7, "30" => 30][$p];
$dari   = date("Y-m-d", strtotime("-" . ($hariPeriode - 1) . " days"));
$namaP  = ["hari" => "hari ini", "7" => "7 hari terakhir", "30" => "30 hari terakhir"][$p];
$auto   = isset($_GET["auto"]);
$pilihD = (int)($_GET["d"] ?? 0);

// alamat halaman ini dengan sebagian parameter diubah (null = hapus)
function u(array $ubah) {
    $q = array_merge($_GET, $ubah);
    foreach ($q as $k => $v) if ($v === null) unset($q[$k]);
    return "admin.php" . ($q ? "?" . http_build_query($q) : "");
}
function satu(PDO $pdo, $sql, array $par = []) {
    $st = $pdo->prepare($sql);
    $st->execute($par);
    return (int)$st->fetchColumn();
}
function singkat($v) {
    if ($v >= 1000000) return round($v / 1000000, 1) . "jt";
    if ($v >= 1000)    return round($v / 1000) . "rb";
    return (string)(int)$v;
}
// grafik garis sederhana (SVG)
function grafikGaris(array $label, array $nilai, $rupiah = false) {
    $w = 600; $h = 200; $pl = 44; $pr = 12; $pt = 14; $pb = 28;
    $n = count($nilai);
    $maks = max(1, max($nilai));
    if (!$rupiah) $maks = max(4, (int)ceil($maks / 4) * 4);   // kelipatan 4 agar angka sumbu bulat
    $px = function ($i) use ($n, $w, $pl, $pr) { return $n > 1 ? $pl + ($w - $pl - $pr) * $i / ($n - 1) : $pl; };
    $py = function ($v) use ($h, $pt, $pb, $maks) { return $h - $pb - ($h - $pt - $pb) * $v / $maks; };
    $o = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" role="img">';
    for ($g = 0; $g <= 4; $g++) {
        $v = $maks * $g / 4; $y = round($py($v), 1);
        $o .= '<line x1="' . $pl . '" x2="' . ($w - $pr) . '" y1="' . $y . '" y2="' . $y . '" stroke="var(--line)"/>';
        $o .= '<text x="' . ($pl - 6) . '" y="' . ($y + 4) . '" text-anchor="end">' . ($rupiah ? singkat($v) : round($v)) . '</text>';
    }
    $pts = [];
    foreach ($nilai as $i => $v) $pts[] = round($px($i), 1) . "," . round($py($v), 1);
    $o .= '<polygon points="' . round($px(0), 1) . ',' . ($h - $pb) . ' ' . implode(" ", $pts) . ' ' . round($px($n - 1), 1) . ',' . ($h - $pb) . '" fill="#e8b923" opacity=".18"/>';
    $o .= '<polyline points="' . implode(" ", $pts) . '" fill="none" stroke="#e8b923" stroke-width="2.5"/>';
    $langkah = max(1, (int)ceil($n / 7));
    foreach ($nilai as $i => $v) {
        $o .= '<circle cx="' . round($px($i), 1) . '" cy="' . round($py($v), 1) . '" r="4" fill="#e8b923"><title>' . e($label[$i]) . ': ' . ($rupiah ? rp($v) : $v) . '</title></circle>';
        if ($i % $langkah === 0 || $i === $n - 1) {
            $o .= '<text x="' . round($px($i), 1) . '" y="' . ($h - 8) . '" text-anchor="' . ($i === $n - 1 ? 'end' : 'middle') . '">' . e($label[$i]) . '</text>';
        }
    }
    return $o . '</svg>';
}

// ---------- angka ringkasan ----------
$checkin   = satu($pdo, "SELECT COUNT(*) FROM checkins WHERE result='success' AND DATE(scanned_at)>=?", [$dari]);
$terjadwal = satu($pdo, "SELECT COALESCE(SUM(qty),0) FROM orders WHERE status='paid' AND visit_date=?", [$hari]);
$terjual   = satu($pdo, "SELECT COALESCE(SUM(qty),0) FROM orders WHERE status='paid' AND DATE(created_at)>=?", [$dari]);
$pendapat  = satu($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid' AND DATE(created_at)>=?", [$dari]);

// ---------- grafik: 7 hari, atau 30 hari kalau dipilih ----------
$n = ($p === "30") ? 30 : 7;
$awalGrafik = date("Y-m-d", strtotime("-" . ($n - 1) . " days"));
$st = $pdo->prepare("SELECT DATE(scanned_at), COUNT(*) FROM checkins WHERE result='success' AND DATE(scanned_at)>=? GROUP BY DATE(scanned_at)");
$st->execute([$awalGrafik]);
$kunj = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$st = $pdo->prepare("SELECT DATE(created_at), SUM(total) FROM orders WHERE status='paid' AND DATE(created_at)>=? GROUP BY DATE(created_at)");
$st->execute([$awalGrafik]);
$rev = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$lab = []; $vKunj = []; $vRev = [];
for ($i = $n - 1; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-$i days"));
    $lab[]   = date("d/m", strtotime($d));
    $vKunj[] = (int)($kunj[$d] ?? 0);
    $vRev[]  = (int)($rev[$d] ?? 0);
}

// ---------- destinasi terpopuler (tiket terjual pada periode) ----------
$st = $pdo->prepare(
    "SELECT d.id, d.name, COALESCE(SUM(o.qty),0) AS total
     FROM destinations d LEFT JOIN sessions s ON s.destination_id = d.id
     LEFT JOIN orders o ON o.session_id = s.id AND o.status = 'paid' AND DATE(o.created_at) >= ?
     GROUP BY d.id, d.name ORDER BY total DESC, d.name"
);
$st->execute([$dari]);
$populer = $st->fetchAll();
$pm = max(1, (int)($populer[0]["total"] ?? 1));

// ---------- kuota sesi hari ini ----------
$st = $pdo->prepare(
    "SELECT d.name, s.start_time, s.quota, COALESCE(SUM(o.qty),0) AS terpakai
     FROM sessions s JOIN destinations d ON d.id = s.destination_id
     LEFT JOIN orders o ON o.session_id = s.id AND o.visit_date = ? AND o.status = 'paid'
     GROUP BY s.id, d.name, s.start_time, s.quota
     HAVING terpakai > 0 ORDER BY d.name, s.start_time"
);
$st->execute([$hari]);
$kuota = $st->fetchAll();

// ---------- rincian satu destinasi (jika diklik) ----------
$rinci = null;
if ($pilihD) {
    $st = $pdo->prepare("SELECT * FROM destinations WHERE id = ?");
    $st->execute([$pilihD]);
    $rinci = $st->fetch();
    if ($rinci) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(o.qty),0), COALESCE(SUM(o.total),0) FROM orders o JOIN sessions s ON s.id=o.session_id WHERE s.destination_id=? AND o.status='paid' AND DATE(o.created_at)>=?");
        $st->execute([$pilihD, $dari]);
        [$rTiket, $rUang] = $st->fetch(PDO::FETCH_NUM);
        $rMasuk = satu($pdo, "SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id=t.order_id JOIN sessions s ON s.id=o.session_id WHERE s.destination_id=? AND t.status='used' AND DATE(t.used_at)>=?", [$pilihD, $dari]);
        $st = $pdo->prepare(
            "SELECT s.start_time, s.quota, COALESCE(SUM(o.qty),0) AS terpakai
             FROM sessions s LEFT JOIN orders o ON o.session_id = s.id AND o.visit_date = ? AND o.status = 'paid'
             WHERE s.destination_id = ? GROUP BY s.id, s.start_time, s.quota ORDER BY s.start_time"
        );
        $st->execute([$hari, $pilihD]);
        $rSesi = $st->fetchAll();
    }
}

// ---------- pesanan terbaru ----------
$sql = "SELECT o.order_code, o.visit_date, o.qty, o.total, o.status, o.created_at,
               u.name AS wisatawan, d.name AS dname, s.start_time
        FROM orders o JOIN users u ON u.id = o.user_id
        JOIN sessions s ON s.id = o.session_id JOIN destinations d ON d.id = s.destination_id"
     . ($rinci ? " WHERE d.id = ?" : "") . " ORDER BY o.id DESC LIMIT 10";
$st = $pdo->prepare($sql);
$st->execute($rinci ? [$pilihD] : []);
$terbaru = $st->fetchAll();
$namaStatus = ["paid" => "Lunas", "pending" => "Menunggu bayar", "expired" => "Kedaluwarsa", "cancelled" => "Batal"];

$skrip = $auto ? '<script>setTimeout(function(){location.reload()},5000)</script>' : '';
$judul = "Dashboard admin";
require_once "header.php";
?>
<style>
.bar-atas{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}
.alat{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.filter{display:inline-flex;gap:2px;padding:3px;background:var(--card);border:1px solid var(--line);border-radius:12px}
.filter a{padding:8px 16px;border-radius:9px;color:var(--ink);text-decoration:none;font-weight:600;font-size:.92rem}
.filter a:hover{background:var(--line)}
.filter a.aktif{background:var(--green);color:#fff}
.otomatis{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid var(--line);border-radius:12px;background:var(--card);color:var(--ink);text-decoration:none;font-weight:600;font-size:.92rem}
.otomatis .titik{width:9px;height:9px;border-radius:50%;background:var(--mute)}
.otomatis.aktif{border-color:var(--green)}
.otomatis.aktif .titik{background:#3ecf8e;box-shadow:0 0 0 3px rgba(62,207,142,.25)}
.stats small{display:block}
.baris a{font-weight:600}
svg text{fill:var(--mute);font-size:11px;font-family:inherit}
.gulir{overflow-x:auto}
.tabel{width:100%;border-collapse:collapse;font-size:.92rem}
.tabel th,.tabel td{text-align:left;padding:8px 12px;border-bottom:1px solid var(--line);white-space:nowrap}
.tabel th{color:var(--mute);font-weight:600}
.st{display:inline-block;padding:1px 10px;border-radius:99px;font-size:.82rem;font-weight:600;background:var(--line)}
.st-paid{background:#d6f0e2;color:#0d4a2c}
.st-pending{background:#fbeec2;color:#5c4400}
.st-expired,.st-cancelled{background:#fbdcd7;color:#7a1d14}
</style>
<div class="bar-atas">
  <h2>Dashboard kunjungan</h2>
  <div class="alat">
    <div class="filter" role="group" aria-label="Periode">
      <?php foreach (["hari" => "Hari ini", "7" => "7 hari", "30" => "30 hari"] as $k => $t): ?>
        <a href="<?= e(u(["p" => $k])) ?>" class="<?= $p === (string)$k ? "aktif" : "" ?>"><?= $t ?></a>
      <?php endforeach; ?>
    </div>
    <a href="<?= e(u(["auto" => $auto ? null : 1])) ?>" class="otomatis <?= $auto ? "aktif" : "" ?>"><span class="titik"></span><?= $auto ? "Otomatis: nyala" : "Otomatis: mati" ?></a>
  </div>
</div>

<div class="stats">
  <div class="card in"><span class="mute">Check-in (<?= e($namaP) ?>)</span><b><?= $checkin ?></b></div>
  <div class="card in"><span class="mute">Kunjungan terjadwal hari ini</span><b><?= $terjadwal ?></b></div>
  <div class="card in"><span class="mute">Tiket terjual (<?= e($namaP) ?>)</span><b><?= $terjual ?></b></div>
  <div class="card in"><span class="mute">Pendapatan (<?= e($namaP) ?>)</span><b><?= rp($pendapat) ?></b></div>
</div>

<div class="grid2">
  <section class="card in">
    <h3>Kunjungan per hari (<?= $n ?> hari terakhir)</h3>
    <?= grafikGaris($lab, $vKunj) ?>
  </section>
  <section class="card in">
    <h3>Pendapatan per hari (<?= $n ?> hari terakhir)</h3>
    <?= grafikGaris($lab, $vRev, true) ?>
  </section>
</div>

<div class="grid2">
  <section class="card in">
    <h3>Destinasi terpopuler (<?= e($namaP) ?>)</h3>
    <?php foreach ($populer as $x): ?>
      <div class="bar-item">
        <div class="baris"><a href="<?= e(u(["d" => $x["id"]])) ?>#rincian"><?= e($x["name"]) ?></a><b><?= (int)$x["total"] ?></b></div>
        <div class="bar"><i style="width:<?= round($x["total"] / $pm * 100) ?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card in">
    <h3>Kuota sesi hari ini</h3>
    <?php if (!$kuota): ?><p class="mute">Belum ada pemesanan untuk hari ini.</p><?php endif; ?>
    <?php foreach ($kuota as $k): ?>
      <div class="bar-item">
        <div class="baris"><span><?= e($k["name"]) ?> <?= jam($k["start_time"]) ?></span><span><?= (int)$k["terpakai"] ?>/<?= (int)$k["quota"] ?></span></div>
        <div class="bar"><i style="width:<?= round($k["terpakai"] / $k["quota"] * 100) ?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php if ($rinci): ?>
<section class="card in" id="rincian">
  <div class="bar-atas">
    <h3>Rincian: <?= e($rinci["name"]) ?></h3>
    <a class="btn" href="<?= e(u(["d" => null])) ?>">Tutup rincian</a>
  </div>
  <div class="stats">
    <div><span class="mute">Harga tiket</span><b><?= rp($rinci["price"]) ?></b></div>
    <div><span class="mute">Tiket terjual (<?= e($namaP) ?>)</span><b><?= (int)$rTiket ?></b></div>
    <div><span class="mute">Pendapatan</span><b><?= rp($rUang) ?></b></div>
    <div><span class="mute">Sudah check-in</span><b><?= (int)$rMasuk ?></b></div>
  </div>
  <h3>Sesi hari ini</h3>
  <?php foreach ($rSesi as $s): ?>
    <div class="bar-item">
      <div class="baris"><span>Sesi <?= jam($s["start_time"]) ?></span><span><?= (int)$s["terpakai"] ?>/<?= (int)$s["quota"] ?></span></div>
      <div class="bar"><i style="width:<?= round($s["terpakai"] / $s["quota"] * 100) ?>%"></i></div>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card in">
  <h3>Pesanan terbaru<?= $rinci ? " (" . e($rinci["name"]) . ")" : "" ?></h3>
  <?php if (!$terbaru): ?><p class="mute">Belum ada pesanan.</p><?php else: ?>
  <div class="gulir">
    <table class="tabel">
      <thead><tr><th>Kode</th><th>Wisatawan</th><th>Destinasi</th><th>Kunjungan</th><th>Tiket</th><th>Status</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($terbaru as $o): ?>
        <tr>
          <td><code><?= e($o["order_code"]) ?></code></td>
          <td><?= e($o["wisatawan"]) ?></td>
          <td><?= e($o["dname"]) ?></td>
          <td><?= e(tgl($o["visit_date"])) ?>, <?= jam($o["start_time"]) ?></td>
          <td><?= (int)$o["qty"] ?></td>
          <td><span class="st st-<?= e($o["status"]) ?>"><?= e($namaStatus[$o["status"]] ?? $o["status"]) ?></span></td>
          <td><?= rp($o["total"]) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php require_once "footer.php"; ?>
