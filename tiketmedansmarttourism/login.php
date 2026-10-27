<?php
require_once "auth.php";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $st->execute([trim($_POST["email"] ?? "")]);
    $row = $st->fetch();

    if ($row && password_verify($_POST["password"] ?? "", $row["password"])) {
        session_regenerate_id(true);
        $_SESSION["user"] = ["id" => (int)$row["id"], "name" => $row["name"], "role" => $row["role"]];

        $next = $_GET["next"] ?? "";
        // hanya terima alamat lokal agar tidak dialihkan ke situs lain
        if ($next !== "" && $next[0] === "/" && strpos($next, "//") !== 0) pindah($next);

        if ($row["role"] === "admin") pindah("admin.php");
        if ($row["role"] === "petugas") pindah("petugas.php");
        pindah("index.php");
    }
    $error = "Email atau kata sandi salah.";
}

$judul = "Masuk";
require_once "header.php";
?>
<div class="card in narrow">
  <h2>Masuk</h2>
  <?php if ($error): ?><p class="alert err"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Email <input type="email" name="email" required></label>
    <label>Kata sandi <input type="password" name="password" required></label>
    <button class="btn" type="submit">Masuk</button>
  </form>
  <p class="mute">Belum punya akun? <a href="daftar.php">Daftar</a></p>
</div>
<?php require_once "footer.php"; ?>
