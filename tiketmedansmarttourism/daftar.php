<?php
require_once "auth.php";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama  = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $pass  = $_POST["password"] ?? "";

    if ($nama === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Nama dan email harus diisi dengan benar.";
    } elseif (strlen($pass) < 6) {
        $error = "Kata sandi minimal 6 karakter.";
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?, 'wisatawan')");
            $st->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            pindah("login.php");
        } catch (PDOException $ex) {
            $error = ($ex->getCode() === "23000") ? "Email sudah terdaftar." : "Pendaftaran gagal.";
        }
    }
}

$judul = "Daftar";
require_once "header.php";
?>
<div class="card in narrow">
  <h2>Daftar akun wisatawan</h2>
  <?php if ($error): ?><p class="alert err"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Nama <input type="text" name="name" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Kata sandi (min. 6 karakter) <input type="password" name="password" required></label>
    <button class="btn" type="submit">Daftar</button>
  </form>
</div>
<?php require_once "footer.php"; ?>
