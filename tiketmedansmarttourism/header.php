<?php
require_once __DIR__ . "/auth.php";
$u = user();
$judul = $judul ?? "Medan Smart Tourism";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($judul) ?></title>
  <script>(function(){var t="light";try{if(localStorage.getItem("tema")==="dark")t="dark"}catch(e){}document.documentElement.setAttribute("data-theme",t)})();</script>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
  <header class="top">
    <a class="brand" href="index.php">Medan Smart Tourism <small>Tiket digital dan antrean wisata Kota Medan</small></a>
    <nav>
      <a href="index.php">Wisata</a>
      <?php if ($u && $u["role"] === "wisatawan"): ?><a href="tiket.php">Tiket saya</a><?php endif; ?>
      <?php if ($u && in_array($u["role"], ["petugas","admin"])): ?><a href="petugas.php">Petugas</a><?php endif; ?>
      <?php if ($u && $u["role"] === "admin"): ?><a href="admin.php">Dashboard</a><?php endif; ?>
      <?php if ($u): ?>
        <span class="mute">Halo, <?= e($u["name"]) ?></span> <a href="logout.php">Keluar</a>
      <?php else: ?>
        <a href="login.php">Masuk</a> <a href="daftar.php">Daftar</a>
      <?php endif; ?>
      <button type="button" class="tema" id="tema" aria-label="Ganti tema terang atau gelap">Tema</button>
    </nav>
    <script>document.getElementById("tema").onclick=function(){var d=document.documentElement,n=d.getAttribute("data-theme")==="dark"?"light":"dark";d.setAttribute("data-theme",n);try{localStorage.setItem("tema",n)}catch(e){}};</script>
  </header>
  <main>
